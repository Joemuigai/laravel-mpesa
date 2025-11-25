# M-Pesa Callback Setup

This package provides the tools to handle M-Pesa callbacks securely, but you control the implementation in your application.

## Quick Setup

### 1. Publish the Callback Files

The package provides publishable stubs for the callback controller and routes:

```bash
# Publish callback controller
php artisan vendor:publish --tag=laravel-mpesa-controller

# Publish callback routes
php artisan vendor:publish --tag=laravel-mpesa-routes
```

This will create:

-   `app/Http/Controllers/MpesaCallbackController.php`
-   `routes/mpesa.php`

### 2. Register the Routes (API Routes)

**IMPORTANT**: Callbacks should be registered as API routes, not web routes.

**Laravel 11+** (`bootstrap/app.php`):

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function () {
        Route::prefix('api/webhooks/payments')  // Use neutral terms
            ->middleware('api')
            ->group(base_path('routes/payments.php'));
    },
)
```

**Laravel 10** (`app/Providers/RouteServiceProvider.php`):

```php
public function boot(): void
{
    $this->routes(function () {
        Route::prefix('api/webhooks/payments')  // Use neutral terms
            ->middleware('api')
            ->group(base_path('routes/payments.php'));
    });
}
```

### 3. Run Migrations

```bash
php artisan migrate
```

This creates the `mpesa_callbacks` table for storing all incoming callbacks.

### 4. Update Safaricom Dashboard

⚠️ **CRITICAL**: Do NOT use keywords like "mpesa", "safaricom" in your callback URLs.  
Safaricom recommends using neutral terms like "payments", "webhooks", "transactions", etc.

Configure your callback URLs in the Daraja portal:

**STK Push**:

```
https://yourdomain.com/api/webhooks/payments/stk-push
```

**C2B Confirmation & Validation**:

```
Confirmation: https://yourdomain.com/api/webhooks/payments/c2b/confirmation
Validation: https://yourdomain.com/api/webhooks/payments/c2b/validation
```

**B2C, B2B, etc.**:

```
Result: https://yourdomain.com/api/webhooks/payments/b2c/result
Timeout: https://yourdomain.com/api/webhooks/payments/b2c/timeout
```

## Security

The package provides `VerifyMpesaCallback` middleware that:

-   ✅ Verifies requests come from Safaricom gateway IPs
-   ✅ Logs suspicious attempts
-   ✅ Can be disabled for local development

### Disable IP Verification (Development Only)

In `.env`:

```env
MPESA_VERIFY_CALLBACK_IP=false
```

## Customization

### Modify Callback Logic

Edit `app/Http/Controllers/MpesaCallbackController.php`:

```php
protected function handleCallback(Request $request, string $callbackType): JsonResponse
{
    // Your custom logic here

    // Example: Dispatch events
    event(new MpesaCallbackReceived($normalized));

    //  Example: Queue processing
    ProcessMpesaCallback::dispatch($callback);

    return response()->json([
        'ResultCode' => 0,
        'ResultDesc' => 'Success',
    ]);
}
```

### Add Custom Routes

Edit `routes/mpesa.php` to add custom endpoints or modify middleware.

## What's Provided by the Package

The package provides these core services:

### 1. MpesaCallback Model

```php
use Joemuigai\LaravelMpesa\Models\MpesaCallback;

// Get unprocessed callbacks
$callbacks = MpesaCallback::unprocessed()->byType('stk_push')->get();

// Mark as processed
$callback->markAsProcessed();

// Get transaction details
$details = $callback->getTransactionDetails();
```

### 2. CallbackParser Service

```php
use Joemuigai\LaravelMpesa\Services\CallbackParser;

$parser = app(CallbackParser::class);
$normalized = $parser->parse('stk_push', $payload);
```

### 3. VerifyMpesaCallback Middleware

Already registered as `mpesa.verify` - use it in your routes.

## Testing

Create tests in `tests/Feature/Mpesa`:

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Joemuigai\LaravelMpesa\Models\MpesaCallback;

uses(RefreshDatabase::class);

it('handles STK push callbacks', function () {
    $payload = [ /* M-Pesa payload */ ];

    $response = $this->postJson('/mpesa/callbacks/stk-push', $payload);

    $response->assertStatus(200);
    expect(MpesaCallback::count())->toBe(1);
});
```

## Processing Callbacks

Create a job to process callbacks:

```php
namespace App\Jobs;

use Joemuigai\LaravelMpesa\Models\MpesaCallback;

class ProcessMpesaCallback
{
    public function handle(MpesaCallback $callback)
    {
        $details = $callback->getTransactionDetails();

        // Update your order, invoice, etc.

        $callback->markAsProcessed();
    }
}
```

Schedule it to run periodically:

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->job(new ProcessUnprocessedCallbacks)->everyMinute();
}
```
