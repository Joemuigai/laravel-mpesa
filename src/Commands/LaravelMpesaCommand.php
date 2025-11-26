<?php

namespace Joemuigai\LaravelMpesa\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;

class LaravelMpesaCommand extends Command
{
    public $signature = 'mpesa:install {--force : Overwrite existing files}';

    public $description = 'Install and configure Laravel M-Pesa package';

    protected array $selectedApis = [];

    protected string $scenario = 'single';

    protected ?string $transactionTypePreference = null;

    public function handle(): int
    {
        info('🇰🇪🇰🇪🇰🇪 DAIMA MIMI MKENYA, MWANANCHI MZALENDO 🇰🇪🇰🇪🇰🇪');
        $this->info("\n  _                               _   __  __       ____\n | |                             | | |  \\/  |     |  _ \\\n | |     __ _ _ __ __ ___   _____| | | \\  / |_____| |_) | ___  ___  __ _\n | |    / _` | '__/ _` \\ \\ / / _ \\ | | |\\/| |_____|  __/ / _ \\/ __|/ _` |\n | |___| (_| | | | (_| |\\ V /  __/ | | |  | |     | |   |  __/\\__ \\ (_| |\n |______\\__,_|_|  \\__,_| \\_/ \\___|_| |_|  |_|     |_|    \\___||___/\\__,_|\n");

        info('🚀 Laravel M-Pesa Installation');

        // Step 1: Select Scenario
        $this->selectScenario();

        // Step 2: Select APIs
        $this->selectApis();

        // Step 2.5: Select Transaction Type (if STK is selected)
        if (in_array('stk', $this->selectedApis)) {
            $this->selectTransactionType();
        }

        // Step 3: Publish Config
        $this->publishConfig();

        // Step 4: Publish Migrations (if multi-tenant)
        if ($this->scenario === 'multi_tenant') {
            $this->publishMigrations();
        }

        // Step 5: Publish Example Model (if multi-tenant)
        if ($this->scenario === 'multi_tenant') {
            $this->publishExampleModel();
        }

        // Step 6: Setup Callbacks (Optional)
        $this->setupCallbacks();

        // Step 7: Handle environment variables
        $this->handleEnvironmentVariables();

        // Step 8: Publish additional resources
        $this->publishAdditionalResources();

        // Step 9: Register Middleware
        $this->registerMiddleware();

        // Step 10: Show next steps
        $this->showNextSteps();

        return self::SUCCESS;
    }

    protected function selectScenario(): void
    {
        $this->scenario = select(
            label: 'What type of project are you building?',
            options: [
                'single' => 'Single Account - One business/merchant',
                'multi_tenant' => 'Multi-Tenant SaaS - Multiple businesses/merchants',
                'hybrid' => 'Hybrid - Single business with multiple shortcodes/branches',
            ],
            default: 'single',
            hint: 'This determines how credentials are managed'
        );
    }

    protected function selectApis(): void
    {
        $this->selectedApis = multiselect(
            label: 'Which M-Pesa APIs will you be using?',
            options: [
                'stk' => 'STK Push (Lipa Na M-Pesa Online) - Customer payment prompt',
                'c2b' => 'C2B (Customer to Business) - Customer pays to paybill/till',
                'b2c' => 'B2C (Business to Customer) - Send money to customers',
                'b2b' => 'B2B (Business to Business) - Send money to businesses',
                'transaction_status' => 'Transaction Status - Query transaction status',
                'account_balance' => 'Account Balance - Check account balance',
                'reversal' => 'Reversal - Reverse a transaction',
                'dynamic_qr' => 'Dynamic QR Code - Generate QR codes',
                'pull_transaction' => 'Pull Transaction - Query transactions',
            ],
            default: ['stk'],
            hint: 'Select all that apply. You can always add more later.',
            required: true
        );
    }

    protected function selectTransactionType(): void
    {
        // Only offer "both" for multi-tenant/hybrid scenarios
        if ($this->scenario === 'single') {
            $this->transactionTypePreference = select(
                label: 'What type of M-Pesa account will you use for STK Push?',
                options: [
                    'paybill' => 'Paybill - Business shortcode with account references',
                    'till' => 'Till Number (Buy Goods) - Retail till number',
                ],
                default: 'paybill',
                hint: 'Choose the type that matches your M-Pesa account'
            );
        } else {
            $this->transactionTypePreference = select(
                label: 'What type of M-Pesa accounts will your platform handle?',
                options: [
                    'paybill' => 'Paybill only - All accounts use paybill',
                    'till' => 'Till Number only - All accounts use till numbers',
                    'both' => 'Both - Different accounts use different types',
                ],
                default: 'both',
                hint: 'For multi-tenant, you can support different account types'
            );
        }
    }

    protected function setupCallbacks(): void
    {
        $setupCallbacks = confirm(
            label: 'Do you want to set up M-Pesa callback handling?',
            default: true,
            hint: 'This will publish a controller and routes for handling webhooks'
        );

        if (! $setupCallbacks) {
            return;
        }

        spin(
            callback: function () {
                // Publish Controller
                $this->callSilent('vendor:publish', [
                    '--tag' => 'laravel-mpesa-controller',
                ]);

                // Publish Routes
                $this->callSilent('vendor:publish', [
                    '--tag' => 'laravel-mpesa-routes',
                ]);

                // Publish Migration
                $this->callSilent('vendor:publish', [
                    '--tag' => 'laravel-mpesa-migrations',
                ]);
            },
            message: 'Publishing callback files...'
        );

        $this->components->info('Callback files published successfully!');
        $this->components->bulletList([
            'Controller: app/Http/Controllers/MpesaCallbackController.php',
            'Routes: routes/mpesa.php',
            'Migration: database/migrations/create_mpesa_callbacks_table.php',
        ]);

        $this->components->warn('IMPORTANT: Register the routes in bootstrap/app.php as API routes!');
    }

    protected function publishConfig(): void
    {
        $configPath = config_path('mpesa.php');

        if (File::exists($configPath) && ! $this->option('force')) {
            $overwrite = confirm(
                label: 'Config file already exists. Overwrite?',
                default: false
            );

            if (! $overwrite) {
                $this->components->info('Skipping config file...');

                return;
            }
        }

        spin(
            callback: fn () => $this->generateConfig(),
            message: 'Generating configuration file...'
        );

        $this->components->info('Configuration published to: config/mpesa.php');
    }

    protected function generateConfig(): void
    {
        $config = $this->buildConfigArray();
        $configContent = "<?php\n\n// M-Pesa Configuration - Generated by mpesa:install\nreturn ".$this->varExport($config).";\n";

        File::ensureDirectoryExists(config_path());
        File::put(config_path('mpesa.php'), $configContent);
    }

    protected function buildConfigArray(): array
    {
        $config = [];

        // Account driver based on scenario
        if ($this->scenario === 'multi_tenant') {
            $config['accounts'] = [
                'driver' => new MpesaComment('database', 'Use database driver for multi-tenant applications'),
                'model' => new MpesaComment('App\\Models\\MpesaAccount', 'The Eloquent model for M-Pesa accounts'),
                'cache_ttl' => new MpesaComment(300, 'Cache duration for account credentials in seconds'),
            ];
        } else {
            $config['accounts'] = [
                'driver' => new MpesaComment('single', 'Use single driver for single account applications'),
                'model' => null,
                'cache_ttl' => 300,
            ];
        }

        // Core credentials (always needed)
        $config['credentials'] = [
            'consumer_key' => new MpesaComment(new MpesaEnv('MPESA_CONSUMER_KEY'), 'Your M-Pesa Consumer Key'),
            'consumer_secret' => new MpesaComment(new MpesaEnv('MPESA_CONSUMER_SECRET'), 'Your M-Pesa Consumer Secret'),
        ];

        $config['environment'] = new MpesaComment(new MpesaEnv('MPESA_ENVIRONMENT', 'sandbox'), 'M-Pesa Environment: sandbox or production');

        $config['base_urls'] = [
            'sandbox' => 'https://sandbox.safaricom.co.ke',
            'production' => 'https://api.safaricom.co.ke',
        ];

        // Add API-specific configs
        if (in_array('stk', $this->selectedApis)) {
            $config['stk'] = $this->getStkConfig();
        }

        if (in_array('c2b', $this->selectedApis)) {
            $config['c2b'] = $this->getC2bConfig();
        }

        if (in_array('b2c', $this->selectedApis) || in_array('b2b', $this->selectedApis)) {
            $config['initiator'] = $this->getInitiatorConfig();
        }

        if (in_array('b2c', $this->selectedApis)) {
            $config['b2c'] = $this->getB2cConfig();
        }

        if (in_array('transaction_status', $this->selectedApis)) {
            $config['transaction_status'] = $this->getTransactionStatusConfig();
        }

        if (in_array('pull_transaction', $this->selectedApis)) {
            $config['pull'] = $this->getPullConfig();
        }

        // Callbacks (if any async APIs selected)
        $asyncApis = array_intersect($this->selectedApis, ['b2c', 'b2b', 'transaction_status', 'account_balance', 'reversal']);
        if (! empty($asyncApis)) {
            $config['callbacks'] = $this->getCallbacksConfig($asyncApis);
        }

        // Security (for B2C/B2B/Reversal/Status)
        if (array_intersect($this->selectedApis, ['b2c', 'b2b', 'reversal', 'transaction_status', 'account_balance'])) {
            $config['security'] = $this->getSecurityConfig();
        }

        // HTTP config (always needed)
        $config['http'] = [
            'timeout' => new MpesaComment(new MpesaEnv('MPESA_HTTP_TIMEOUT', 30), 'HTTP request timeout in seconds'),
            'connect_timeout' => new MpesaComment(new MpesaEnv('MPESA_HTTP_CONNECT_TIMEOUT', 10), 'HTTP connection timeout in seconds'),
            'retries' => new MpesaComment(new MpesaEnv('MPESA_HTTP_RETRIES', 3), 'Number of retries for failed requests'),
            'verify' => new MpesaComment(new MpesaEnv('MPESA_HTTP_VERIFY', true), 'Verify SSL certificates'),
        ];

        return $config;
    }

    protected function getStkConfig(): array
    {
        $config = [
            'shortcode' => new MpesaComment(new MpesaEnv('MPESA_STK_SHORTCODE', new MpesaEnv('MPESA_BUSINESS_SHORTCODE', '174379')), 'The shortcode for STK Push (Paybill or Buy Goods)'),
            'passkey' => new MpesaComment(new MpesaEnv('MPESA_STK_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'), 'Lipa Na M-Pesa Online Passkey'),
            'callback_url' => new MpesaComment(new MpesaEnv('MPESA_STK_CALLBACK_URL', new MpesaEnv('MPESA_CALLBACK_URL')), 'The URL where M-Pesa will send the callback'),
        ];

        // Set default_type based on user selection
        if ($this->transactionTypePreference === 'till') {
            $config['default_type'] = new MpesaComment(new MpesaEnv('MPESA_STK_DEFAULT_TYPE', 'buy_goods'), 'Default transaction type: paybill or buy_goods');
        } elseif ($this->transactionTypePreference === 'both') {
            $config['default_type'] = new MpesaComment(new MpesaEnv('MPESA_STK_DEFAULT_TYPE', 'paybill'), 'Default transaction type: paybill or buy_goods'); // Default to paybill when both
        } else {
            $config['default_type'] = new MpesaComment(new MpesaEnv('MPESA_STK_DEFAULT_TYPE', 'paybill'), 'Default transaction type: paybill or buy_goods');
        }

        // Only include transaction_types if user selected 'both' or if they might need it
        if ($this->transactionTypePreference === 'both') {
            $config['transaction_types'] = [
                'paybill' => 'CustomerPayBillOnline',
                'buy_goods' => 'CustomerBuyGoodsOnline',
            ];
        } elseif ($this->transactionTypePreference === 'till') {
            $config['transaction_types'] = [
                'buy_goods' => 'CustomerBuyGoodsOnline',
            ];
        } else {
            $config['transaction_types'] = [
                'paybill' => 'CustomerPayBillOnline',
            ];
        }

        $config['defaults'] = [
            'account_reference' => new MpesaComment(new MpesaEnv('MPESA_STK_DEFAULT_ACCOUNT_REF', 'Payment'), 'Default account reference'),
            'transaction_desc' => new MpesaComment(new MpesaEnv('MPESA_STK_DEFAULT_DESC', 'Payment'), 'Default transaction description'),
        ];

        return $config;
    }

    protected function getC2bConfig(): array
    {
        return [
            'shortcode' => new MpesaComment(new MpesaEnv('MPESA_C2B_SHORTCODE', new MpesaEnv('MPESA_BUSINESS_SHORTCODE')), 'The shortcode for C2B transactions'),
            'validation_url' => new MpesaComment(new MpesaEnv('MPESA_C2B_VALIDATION_URL'), 'URL for validating C2B transactions'),
            'confirmation_url' => new MpesaComment(new MpesaEnv('MPESA_C2B_CONFIRMATION_URL'), 'URL for confirming C2B transactions'),
            'response_type' => new MpesaComment(new MpesaEnv('MPESA_C2B_RESPONSE_TYPE', 'Completed'), 'Default response type: Completed or Cancelled'),
        ];
    }

    protected function getInitiatorConfig(): array
    {
        return [
            'name' => new MpesaComment(new MpesaEnv('MPESA_INITIATOR_NAME', 'testapi'), 'Initiator username for B2C/B2B'),
            'password' => new MpesaComment(new MpesaEnv('MPESA_INITIATOR_PASSWORD'), 'Initiator password for B2C/B2B'),
        ];
    }

    protected function getB2cConfig(): array
    {
        return [
            'shortcode' => new MpesaComment(new MpesaEnv('MPESA_B2C_SHORTCODE', new MpesaEnv('MPESA_BUSINESS_SHORTCODE')), 'The shortcode for B2C transactions'),
            'command_id' => new MpesaComment(new MpesaEnv('MPESA_B2C_COMMAND_ID', 'BusinessPayment'), 'Default command ID: BusinessPayment, SalaryPayment, PromotionPayment'),
            'result_url' => new MpesaComment(new MpesaEnv('MPESA_B2C_RESULT_URL'), 'URL for B2C result callback'),
            'timeout_url' => new MpesaComment(new MpesaEnv('MPESA_B2C_TIMEOUT_URL'), 'URL for B2C timeout callback'),
            'defaults' => [
                'remarks' => new MpesaComment(new MpesaEnv('MPESA_B2C_DEFAULT_REMARKS', 'B2C Payment'), 'Default remarks'),
                'occasion' => new MpesaComment(new MpesaEnv('MPESA_B2C_DEFAULT_OCCASION'), 'Default occasion'),
            ],
        ];
    }

    protected function getTransactionStatusConfig(): array
    {
        return [
            'party_a' => new MpesaComment(new MpesaEnv('MPESA_TXN_STATUS_PARTY_A', new MpesaEnv('MPESA_BUSINESS_SHORTCODE')), 'The shortcode/party initiating the status query'),
            'result_url' => new MpesaComment(new MpesaEnv('MPESA_STATUS_RESULT_URL'), 'URL for status query result callback'),
            'timeout_url' => new MpesaComment(new MpesaEnv('MPESA_STATUS_TIMEOUT_URL'), 'URL for status query timeout callback'),
            'defaults' => [
                'remarks' => 'Transaction Status Query',
                'occasion' => null,
            ],
        ];
    }

    protected function getPullConfig(): array
    {
        return [
            'shortcode' => new MpesaComment(new MpesaEnv('MPESA_PULL_SHORTCODE', new MpesaEnv('MPESA_BUSINESS_SHORTCODE')), 'The shortcode for Pull transactions'),
            'register' => [
                'request_type' => new MpesaComment(new MpesaEnv('MPESA_PULL_REQUEST_TYPE', 'Pull'), 'Request type: Pull'),
                'nominated_number' => new MpesaComment(new MpesaEnv('MPESA_PULL_NOMINATED_MSISDN'), 'The nominated number for pull transactions'),
                'callback_url' => new MpesaComment(new MpesaEnv('MPESA_PULL_CALLBACK_URL', new MpesaEnv('MPESA_CALLBACK_URL')), 'URL for pull transaction callback'),
            ],
        ];
    }

    protected function getCallbacksConfig(array $apis): array
    {
        $callbacks = [
            'default' => new MpesaComment(new MpesaEnv('MPESA_CALLBACK_URL'), 'Default callback URL'),
        ];

        if (in_array('b2c', $apis)) {
            $callbacks['b2c'] = [
                'result' => new MpesaEnv('MPESA_B2C_RESULT_URL'),
                'timeout' => new MpesaEnv('MPESA_B2C_TIMEOUT_URL'),
            ];
        }

        if (in_array('b2b', $apis)) {
            $callbacks['b2b'] = [
                'result' => new MpesaEnv('MPESA_B2B_RESULT_URL'),
                'timeout' => new MpesaEnv('MPESA_B2B_TIMEOUT_URL'),
            ];
        }

        if (in_array('transaction_status', $apis)) {
            $callbacks['status'] = [
                'result' => new MpesaEnv('MPESA_STATUS_RESULT_URL'),
                'timeout' => new MpesaEnv('MPESA_STATUS_TIMEOUT_URL'),
            ];
        }

        if (in_array('account_balance', $apis)) {
            $callbacks['balance'] = [
                'result' => new MpesaEnv('MPESA_BALANCE_RESULT_URL'),
                'timeout' => new MpesaEnv('MPESA_BALANCE_TIMEOUT_URL'),
            ];
        }

        if (in_array('reversal', $apis)) {
            $callbacks['reversal'] = [
                'result' => new MpesaEnv('MPESA_REVERSAL_RESULT_URL'),
                'timeout' => new MpesaEnv('MPESA_REVERSAL_TIMEOUT_URL'),
            ];
        }

        return $callbacks;
    }

    protected function getSecurityConfig(): array
    {
        return [
            'certificates' => [
                'sandbox' => __DIR__.'/../../Certificates/SandboxCertificate.cer',
                'production' => __DIR__.'/../../Certificates/ProductionCertificate.cer',
            ],
            'cache_ttl' => new MpesaComment(new MpesaEnv('MPESA_SECURITY_CREDENTIAL_CACHE_TTL', 3600), 'Cache duration for security credentials'),
        ];
    }

    protected function publishMigrations(): void
    {
        $migrationExists = collect(File::files(database_path('migrations')))
            ->contains(fn ($file) => str_contains($file->getFilename(), 'create_mpesa_accounts_table'));

        if ($migrationExists && ! $this->option('force')) {
            $this->components->info('Migration already exists. Skipping...');

            return;
        }

        $timestamp = date('Y_m_d_His');
        $migrationPath = database_path("migrations/{$timestamp}_create_laravel_mpesa_tables.php");

        File::copy(
            __DIR__.'/../../database/migrations/create_laravel_mpesa_tables.php',
            $migrationPath
        );

        $this->components->info("Migration published: {$migrationPath}");
    }

    protected function publishExampleModel(): void
    {
        $modelPath = app_path('Models/MpesaAccount.php');

        if (File::exists($modelPath) && ! $this->option('force')) {
            $overwrite = confirm(
                label: 'MpesaAccount model already exists. Overwrite?',
                default: false
            );

            if (! $overwrite) {
                $this->components->info('Skipping model...');

                return;
            }
        }

        File::ensureDirectoryExists(app_path('Models'));
        File::copy(
            __DIR__.'/../../examples/MpesaAccount.php',
            $modelPath
        );

        $this->components->info("Model published: {$modelPath}");
    }

    protected function showNextSteps(): void
    {
        $steps = [
            '✓ Configuration generated',
        ];

        if ($this->scenario === 'multi_tenant') {
            $steps[] = '✓ Migration published';
            $steps[] = '✓ Model published';
        }

        note(implode("\n", $steps), 'Installation Complete!');

        $this->newLine();
        $this->components->twoColumnDetail('Next Steps:', '');
        $this->components->twoColumnDetail('1. Update .env with credentials', 'MPESA_CONSUMER_KEY, MPESA_CONSUMER_SECRET');

        // Transaction type specific guidance
        if (in_array('stk', $this->selectedApis) && $this->transactionTypePreference) {
            if ($this->transactionTypePreference === 'till') {
                $this->components->twoColumnDetail('   STK transaction type:', 'Till Number (Buy Goods) - Set MPESA_STK_DEFAULT_TYPE=buy_goods');
            } elseif ($this->transactionTypePreference === 'both') {
                $this->components->info('   💡 Tip: Use LaravelMpesa::withBuyGoods() or withPaybill() to switch between account types');
            }
        }

        foreach ($this->selectedApis as $api) {
            $envVars = $this->getRequiredEnvVars($api);
            if ($envVars) {
                $this->components->twoColumnDetail("   {$api} requires:", $envVars);
            }
        }

        if ($this->scenario === 'multi_tenant') {
            $this->components->twoColumnDetail('2. Run migrations', 'php artisan migrate');
        }

        $this->newLine();
        $this->components->info('📚 Documentation: https://github.com/joemuigai/laravel-mpesa');
    }

    protected function handleEnvironmentVariables(): void
    {
        $envVars = $this->buildEnvVariables();

        $this->newLine();
        $this->components->info('📋 Required Environment Variables');
        $this->newLine();

        // Display the env vars
        $this->line($envVars);

        $this->newLine();
        $addToEnv = confirm(
            label: 'Would you like to add these variables to your .env file?',
            default: true,
            hint: 'This will append the variables to your .env file. You can edit them later.'
        );

        if ($addToEnv) {
            $this->appendToEnvFile($envVars);
            $this->components->info('✓ Environment variables added to .env file');
        } else {
            $this->components->info('⚠ Remember to manually add these variables to your .env file');
        }
    }

    protected function publishAdditionalResources(): void
    {
        $this->newLine();
        $this->components->info('📦 Additional Resources');

        $resources = multiselect(
            label: 'Which additional resources would you like to publish?',
            options: [
                'events' => 'Events - Publish M-Pesa events to app/Events/Mpesa',
                'service' => 'Service Class - Publish MpesaService for dependency injection',
                'migrations' => 'Migrations - Publish database migrations',
                'stubs' => 'Stubs - Publish package stubs for customization',
            ],
            default: ['service'],
            hint: 'Select resources to copy to your application'
        );

        if (in_array('events', $resources)) {
            $this->call('vendor:publish', [
                '--tag' => 'mpesa-events',
                '--provider' => 'Joemuigai\LaravelMpesa\LaravelMpesaServiceProvider',
            ]);
            $this->components->info('✓ Events published');
        }

        if (in_array('service', $resources)) {
            $this->call('vendor:publish', [
                '--tag' => 'mpesa-service',
                '--provider' => 'Joemuigai\LaravelMpesa\LaravelMpesaServiceProvider',
            ]);
            $this->components->info('✓ MpesaService published to app/Services/Mpesa');
        }

        if (in_array('migrations', $resources)) {
            // Check if we already published migrations via scenario selection
            if ($this->scenario !== 'multi_tenant') {
                $this->call('vendor:publish', [
                    '--tag' => 'laravel-mpesa-migrations',
                    '--provider' => 'Joemuigai\LaravelMpesa\LaravelMpesaServiceProvider',
                ]);
                $this->components->info('✓ Migrations published');
            } else {
                $this->components->info('! Migrations already published via scenario selection');
            }
        }

        if (in_array('stubs', $resources)) {
            $this->call('vendor:publish', [
                '--tag' => 'mpesa-stubs',
                '--provider' => 'Joemuigai\LaravelMpesa\LaravelMpesaServiceProvider',
            ]);
            $this->components->info('✓ Stubs published');
        }
    }

    protected function registerMiddleware(): void
    {
        $bootstrapApp = base_path('bootstrap/app.php');

        if (! file_exists($bootstrapApp)) {
            return;
        }

        $content = file_get_contents($bootstrapApp);

        if (str_contains($content, 'mpesa.verify')) {
            return;
        }

        $this->components->info('Registering middleware...');

        // Pattern to find ->withMiddleware(function (Middleware $middleware) {
        $pattern = '/->withMiddleware\s*\(\s*function\s*\(\s*Middleware\s+\$middleware\s*\)\s*\{/';

        if (preg_match($pattern, $content)) {
            $replacement = "->withMiddleware(function (Middleware \$middleware) {\n        \$middleware->alias([\n            'mpesa.verify' => \Joemuigai\LaravelMpesa\Http\Middleware\VerifyMpesaCallback::class,\n        ]);";

            $newContent = preg_replace($pattern, $replacement, $content);

            if ($newContent !== null && $newContent !== $content) {
                file_put_contents($bootstrapApp, $newContent);
                $this->components->info('✓ Middleware registered in bootstrap/app.php');
            }
        }
    }

    protected function buildEnvVariables(): string
    {
        $lines = [];
        $lines[] = '# ===========================';
        $lines[] = '# M-Pesa Configuration';
        $lines[] = '# Generated by mpesa:install';
        $lines[] = '# ===========================';
        $lines[] = '';

        // Core credentials (always needed)
        $lines[] = '# API Credentials (Required)';
        $lines[] = 'MPESA_CONSUMER_KEY=';
        $lines[] = 'MPESA_CONSUMER_SECRET=';
        $lines[] = 'MPESA_ENVIRONMENT=sandbox  # or production';
        $lines[] = '';

        // Common Defaults
        $lines[] = '# Common Defaults (Optional - used as fallbacks)';
        $lines[] = 'MPESA_BUSINESS_SHORTCODE=';
        $lines[] = 'MPESA_CALLBACK_URL=';
        $lines[] = '';

        // Account driver (if multi-tenant)
        if ($this->scenario === 'multi_tenant') {
            $lines[] = '# Multi-Tenant Configuration';
            $lines[] = 'MPESA_ACCOUNT_DRIVER=database';
            $lines[] = 'MPESA_ACCOUNT_MODEL=App\\Models\\MpesaAccount';
            $lines[] = '';
        }

        // Transaction type specific
        if (in_array('stk', $this->selectedApis)) {
            $lines[] = '# STK Push Configuration';
            $lines[] = 'MPESA_STK_SHORTCODE=';
            $lines[] = 'MPESA_STK_PASSKEY=';
            $lines[] = 'MPESA_STK_CALLBACK_URL=';
            $lines[] = 'MPESA_STK_DEFAULT_ACCOUNT_REF=Payment';
            $lines[] = 'MPESA_STK_DEFAULT_DESC=Payment';

            if ($this->transactionTypePreference === 'till') {
                $lines[] = 'MPESA_STK_DEFAULT_TYPE=buy_goods';
            } elseif ($this->transactionTypePreference === 'both') {
                $lines[] = 'MPESA_STK_DEFAULT_TYPE=paybill  # or buy_goods';
            } else {
                $lines[] = 'MPESA_STK_DEFAULT_TYPE=paybill';
            }
            $lines[] = '';
        }

        if (in_array('c2b', $this->selectedApis)) {
            $lines[] = '# C2B Configuration';
            $lines[] = 'MPESA_C2B_SHORTCODE=';
            $lines[] = 'MPESA_C2B_VALIDATION_URL=';
            $lines[] = 'MPESA_C2B_CONFIRMATION_URL=';
            $lines[] = 'MPESA_C2B_RESPONSE_TYPE=Completed';
            $lines[] = '';
        }

        if (in_array('b2c', $this->selectedApis) || in_array('b2b', $this->selectedApis)) {
            $lines[] = '# Initiator Credentials (B2C/B2B)';
            $lines[] = 'MPESA_INITIATOR_NAME=';
            $lines[] = 'MPESA_INITIATOR_PASSWORD=';
            $lines[] = '';
        }

        if (in_array('b2c', $this->selectedApis)) {
            $lines[] = '# B2C Configuration';
            $lines[] = 'MPESA_B2C_SHORTCODE=';
            $lines[] = 'MPESA_B2C_RESULT_URL=';
            $lines[] = 'MPESA_B2C_TIMEOUT_URL=';
            $lines[] = 'MPESA_B2C_DEFAULT_REMARKS=B2C Payment';
            $lines[] = 'MPESA_B2C_DEFAULT_OCCASION=';
            $lines[] = '';
        }

        if (in_array('b2b', $this->selectedApis)) {
            $lines[] = '# B2B Configuration';
            $lines[] = 'MPESA_B2B_RESULT_URL=';
            $lines[] = 'MPESA_B2B_TIMEOUT_URL=';
            $lines[] = '';
        }

        if (in_array('transaction_status', $this->selectedApis)) {
            $lines[] = '# Transaction Status Configuration';
            $lines[] = 'MPESA_TXN_STATUS_PARTY_A=';
            $lines[] = 'MPESA_STATUS_RESULT_URL=';
            $lines[] = 'MPESA_STATUS_TIMEOUT_URL=';
            $lines[] = '';
        }

        if (in_array('account_balance', $this->selectedApis)) {
            $lines[] = '# Account Balance Configuration';
            $lines[] = 'MPESA_BALANCE_RESULT_URL=';
            $lines[] = 'MPESA_BALANCE_TIMEOUT_URL=';
            $lines[] = '';
        }

        if (in_array('reversal', $this->selectedApis)) {
            $lines[] = '# Reversal Configuration';
            $lines[] = 'MPESA_REVERSAL_RESULT_URL=';
            $lines[] = 'MPESA_REVERSAL_TIMEOUT_URL=';
            $lines[] = '';
        }

        if (in_array('pull_transaction', $this->selectedApis)) {
            $lines[] = '# Pull Transaction Configuration';
            $lines[] = 'MPESA_PULL_SHORTCODE=';
            $lines[] = 'MPESA_PULL_REQUEST_TYPE=Pull';
            $lines[] = 'MPESA_PULL_NOMINATED_MSISDN=';
            $lines[] = 'MPESA_PULL_CALLBACK_URL=';
            $lines[] = '';
        }

        // Security
        $lines[] = '# Security Configuration';
        $lines[] = 'MPESA_SECURITY_CREDENTIAL_CACHE_TTL=3600';
        $lines[] = '';

        // HTTP
        $lines[] = '# HTTP Configuration';
        $lines[] = 'MPESA_HTTP_TIMEOUT=30';
        $lines[] = 'MPESA_HTTP_CONNECT_TIMEOUT=10';
        $lines[] = 'MPESA_HTTP_RETRIES=3';
        $lines[] = 'MPESA_HTTP_VERIFY=true';

        return implode("\n", $lines);
    }

    protected function appendToEnvFile(string $content): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            File::put($envPath, $content);

            return;
        }

        // Check if M-Pesa config already exists
        $existing = File::get($envPath);
        if (str_contains($existing, 'M-Pesa Configuration')) {
            // Don't add duplicates
            return;
        }

        // Add blank lines before appending
        $content = "\n\n".$content;
        File::append($envPath, $content);
    }

    protected function getRequiredEnvVars(string $api): ?string
    {
        return match ($api) {
            'stk' => 'MPESA_STK_SHORTCODE, MPESA_STK_PASSKEY, MPESA_STK_CALLBACK_URL',
            'c2b' => 'MPESA_C2B_SHORTCODE, MPESA_C2B_VALIDATION_URL, MPESA_C2B_CONFIRMATION_URL',
            'b2c' => 'MPESA_B2C_SHORTCODE, MPESA_INITIATOR_NAME, MPESA_INITIATOR_PASSWORD',
            'b2b' => 'MPESA_INITIATOR_NAME, MPESA_INITIATOR_PASSWORD',
            default => null,
        };
    }

    protected function varExport($value, $indent = ''): string
    {
        if ($value instanceof MpesaComment) {
            $exported = $this->varExport($value->value, $indent);
            if ($value->inline) {
                return "{$exported} // {$value->comment}";
            }

            return $exported;
        }

        if ($value instanceof MpesaEnv) {
            $default = $value->default;
            if ($default instanceof MpesaEnv) {
                // Handle nested env calls: env('KEY', env('FALLBACK'))
                $defaultExport = $this->varExport($default, $indent);
            } else {
                $defaultExport = var_export($default, true);
            }

            return "env('{$value->key}', {$defaultExport})";
        }

        if (is_array($value)) {
            $r = "[\n";
            $indent .= '    ';
            foreach ($value as $key => $val) {
                // Handle block comments
                if ($val instanceof MpesaComment && ! $val->inline) {
                    $r .= "\n{$indent}// {$val->comment}\n";
                }

                $r .= $indent;
                if (is_string($key) && preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $key)) {
                    $r .= "'{$key}' => ";
                } elseif (is_string($key)) {
                    $r .= var_export($key, true).' => ';
                }

                // Special handling for inline comments in arrays to ensure comma is before comment
                if ($val instanceof MpesaComment && $val->inline) {
                    $exportedValue = $this->varExport($val->value, $indent);
                    $r .= "{$exportedValue}, // {$val->comment}\n";
                } else {
                    $r .= $this->varExport($val, $indent).",\n";
                }
            }
            $indent = substr($indent, 0, -4);

            return $r.$indent.']';
        }

        return var_export($value, true);
    }
}

class MpesaEnv
{
    public function __construct(
        public string $key,
        public mixed $default = null
    ) {}
}

class MpesaComment
{
    public function __construct(
        public mixed $value,
        public string $comment,
        public bool $inline = true
    ) {}
}
