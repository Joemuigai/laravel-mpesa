# Laravel M-Pesa

```
 _                               _   __  __       ____
| |                             | | |  \/  |     |  _ \
| |     __ _ _ __ __ ___   _____| | | \  / |_____| |_) | ___  ___  __ _
| |    / _` | '__/ _` \ \ / / _ \ | | |\/| |_____|  __/ / _ \/ __|/ _` |
| |___| (_| | | | (_| |\ V /  __/ | | |  | |     | |   |  __/\__ \ (_| |
|______\__,_|_|  \__,_| \_/ \___|_| |_|  |_|     |_|    \___||___/\__,_|

                              by Joemuigai
```

[![Latest Version on Packagist](https://img.shields.io/packagist/v/joemuigai/laravel-mpesa.svg?style=flat-square)](https://packagist.org/packages/joemuigai/laravel-mpesa)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/joemuigai/laravel-mpesa/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/joemuigai/laravel-mpesa/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/joemuigai/laravel-mpesa.svg?style=flat-square)](https://packagist.org/packages/joemuigai/laravel-mpesa)

A comprehensive, production-ready Laravel package for integrating with Safaricom's M-Pesa Daraja API. Built for both single-merchant applications and multi-tenant SaaS platforms.

## Table of Contents

-   [Features](#features)
-   [Requirements](#requirements)
-   [Installation](#installation)
-   [Configuration](#configuration)
-   [Usage](#usage)
-   [Multi-Tenant Usage](#multi-tenant-usage)
-   [Production Checklist](#production-checklist)
-   [Testing](#testing)
-   [Contributing](#contributing)
-   [License](#license)

## Features

✨ **Complete API Coverage** - 11 M-Pesa APIs supported  
🏢 **Multi-Tenant Ready** - Database-driven account management  
💪 **Production Optimized** - HTTP retries, caching, and failsafe mechanisms  
🎯 **Flexible** - Paybill & Till Number (Buy Goods) support  
🔧 **Developer Friendly** - Interactive installation, IDE autocomplete  
🧪 **Well Tested** - Comprehensive test suite  
📚 **Type Safe** - Full PHPDoc annotations

👉 **[View Full Features Guide](FEATURES.md)** for detailed capabilities.

## Requirements

-   PHP 8.4+
-   Laravel 11.0+ or 12.0+

## Installation

Install via Composer:

```bash
composer require joemuigai/laravel-mpesa
```

Run the interactive installation command to set up configuration and migrations:

```bash
php artisan laravel-mpesa:install
```

The wizard will guide you through:

1. **Scenario Selection** (Single vs Multi-tenant)
2. **API Selection** (STK, C2B, B2C, etc.)
3. **Environment Setup**

## Configuration

### Environment Variables

After installation, add your credentials to `.env`:

```env
# Core Credentials
MPESA_CONSUMER_KEY=your_consumer_key
MPESA_CONSUMER_SECRET=your_consumer_secret
MPESA_ENVIRONMENT=sandbox  # or production

# STK Push
MPESA_STK_SHORTCODE=174379
MPESA_STK_PASSKEY=your_passkey
MPESA_STK_CALLBACK_URL=https://yourdomain.com/mpesa/callback
```

See `config/mpesa.php` for all available options.

## Usage

For detailed code examples, payloads, and callback handling, please refer to the **[USAGE.md](USAGE.md)** file.

### Quick Example: STK Push

```php
use Joemuigai\LaravelMpesa\Facades\LaravelMpesa;

try {
    $response = LaravelMpesa::stkPush(
        amount: 100,
        phoneNumber: '254712345678',
        reference: 'INV-001',
        description: 'Payment'
    );
} catch (\Exception $e) {
    // Handle error
}
```

### Supported APIs

-   [STK Push (Lipa Na M-Pesa Online)](USAGE.md#1-stk-push-lipa-na-m-pesa-online)
-   [STK Push Query](USAGE.md#2-stk-push-query)
-   [C2B (Customer to Business)](USAGE.md#3-c2b-customer-to-business)
-   [B2C (Business to Customer)](USAGE.md#4-b2c-business-to-customer)
-   [B2B (Business to Business)](USAGE.md#5-b2b-business-to-business)
-   [Transaction Status](USAGE.md#6-transaction-status)
-   [Account Balance](USAGE.md#7-account-balance)
-   [Reversal](USAGE.md#8-reversal)
-   [Dynamic QR Code](USAGE.md#9-dynamic-qr-code)
-   [Pull Transaction](USAGE.md#10-pull-transaction)

## Multi-Tenant Usage

For SaaS platforms, you can switch accounts dynamically:

```php
// Switch by Tenant ID
LaravelMpesa::forAccount('tenant-123')->stkPush(100, '254712345678');

// Switch by Model
$account = MpesaAccount::find(1);
LaravelMpesa::withAccount($account)->stkPush(100, '254712345678');
```

See the [Multi-Tenant Guide](USAGE.md#11-multi-tenant-usage) for database setup and more examples.

## Production Checklist

Before going live, ensure you have:

-   [ ] Set `MPESA_ENVIRONMENT=production` in `.env`
-   [ ] Updated `MPESA_CONSUMER_KEY` and `MPESA_CONSUMER_SECRET` with production credentials
-   [ ] Enabled SSL verification (`MPESA_HTTP_VERIFY=true`)
-   [ ] Configured **HTTPS** callback URLs (M-Pesa requires valid SSL)
-   [ ] Set up logging to monitor `laravel-mpesa` channel or default logs
-   [ ] Implemented `try-catch` blocks around all API calls
-   [ ] Configured queue workers if processing callbacks asynchronously

## Testing

Run the package test suite:

```bash
composer test
```

## Contributing

Contributions are welcome! Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security

If you discover a security vulnerability, please email joemuigai004@gmail.com.

## Credits

-   [Joemuigai](https://github.com/joemuigai)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

-   **Issues**: [GitHub Issues](https://github.com/joemuigai/laravel-mpesa/issues)
-   **Email**: joemuigai004@gmail.com

---

Made with ❤️ in Kenya 🇰🇪
