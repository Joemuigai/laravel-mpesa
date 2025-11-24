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

        // Step 6: Handle environment variables
        $this->handleEnvironmentVariables();

        // Step 7: Show next steps
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
                'driver' => 'database',
                'model' => 'App\\Models\\MpesaAccount',
                'cache_ttl' => 300,
            ];
        } else {
            $config['accounts'] = [
                'driver' => 'single',
                'model' => null,
                'cache_ttl' => 300,
            ];
        }

        // Core credentials (always needed)
        $config['credentials'] = [
            'consumer_key' => env('MPESA_CONSUMER_KEY'),
            'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        ];

        $config['environment'] = env('MPESA_ENVIRONMENT', 'sandbox');

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
            'timeout' => env('MPESA_HTTP_TIMEOUT', 30),
            'connect_timeout' => env('MPESA_HTTP_CONNECT_TIMEOUT', 10),
            'retries' => env('MPESA_HTTP_RETRIES', 3),
            'verify' => env('MPESA_HTTP_VERIFY', true),
        ];

        return $config;
    }

    protected function getStkConfig(): array
    {
        $config = [
            'shortcode' => env('MPESA_STK_SHORTCODE', env('MPESA_BUSINESS_SHORTCODE', '174379')),
            'passkey' => env('MPESA_STK_PASSKEY', 'bfb279f9aa9bdbcf158e97dd71a467cd2e0c893059b10f78e6b72ada1ed2c919'),
            'callback_url' => env('MPESA_STK_CALLBACK_URL', env('MPESA_CALLBACK_URL')),
        ];

        // Set default_type based on user selection
        if ($this->transactionTypePreference === 'till') {
            $config['default_type'] = env('MPESA_STK_DEFAULT_TYPE', 'buy_goods');
        } elseif ($this->transactionTypePreference === 'both') {
            $config['default_type'] = env('MPESA_STK_DEFAULT_TYPE', 'paybill'); // Default to paybill when both
        } else {
            $config['default_type'] = env('MPESA_STK_DEFAULT_TYPE', 'paybill');
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
            'account_reference' => env('MPESA_STK_DEFAULT_ACCOUNT_REF', 'Payment'),
            'transaction_desc' => env('MPESA_STK_DEFAULT_DESC', 'Payment'),
        ];

        return $config;
    }

    protected function getC2bConfig(): array
    {
        return [
            'shortcode' => env('MPESA_C2B_SHORTCODE', env('MPESA_BUSINESS_SHORTCODE')),
            'validation_url' => env('MPESA_C2B_VALIDATION_URL'),
            'confirmation_url' => env('MPESA_C2B_CONFIRMATION_URL'),
            'response_type' => env('MPESA_C2B_RESPONSE_TYPE', 'Completed'),
        ];
    }

    protected function getInitiatorConfig(): array
    {
        return [
            'name' => env('MPESA_INITIATOR_NAME', 'testapi'),
            'password' => env('MPESA_INITIATOR_PASSWORD'),
        ];
    }

    protected function getB2cConfig(): array
    {
        return [
            'shortcode' => env('MPESA_B2C_SHORTCODE', env('MPESA_BUSINESS_SHORTCODE')),
            'command_id' => env('MPESA_B2C_COMMAND_ID', 'BusinessPayment'),
            'result_url' => env('MPESA_B2C_RESULT_URL'),
            'timeout_url' => env('MPESA_B2C_TIMEOUT_URL'),
            'defaults' => [
                'remarks' => env('MPESA_B2C_DEFAULT_REMARKS', 'B2C Payment'),
                'occasion' => env('MPESA_B2C_DEFAULT_OCCASION'),
            ],
        ];
    }

    protected function getTransactionStatusConfig(): array
    {
        return [
            'party_a' => env('MPESA_TXN_STATUS_PARTY_A', env('MPESA_BUSINESS_SHORTCODE')),
            'result_url' => env('MPESA_STATUS_RESULT_URL'),
            'timeout_url' => env('MPESA_STATUS_TIMEOUT_URL'),
            'defaults' => [
                'remarks' => 'Transaction Status Query',
                'occasion' => null,
            ],
        ];
    }

    protected function getPullConfig(): array
    {
        return [
            'shortcode' => env('MPESA_PULL_SHORTCODE', env('MPESA_BUSINESS_SHORTCODE')),
            'register' => [
                'request_type' => env('MPESA_PULL_REQUEST_TYPE', 'Pull'),
                'nominated_number' => env('MPESA_PULL_NOMINATED_MSISDN'),
                'callback_url' => env('MPESA_PULL_CALLBACK_URL', env('MPESA_CALLBACK_URL')),
            ],
        ];
    }

    protected function getCallbacksConfig(array $apis): array
    {
        $callbacks = [
            'default' => env('MPESA_CALLBACK_URL'),
        ];

        if (in_array('b2c', $apis)) {
            $callbacks['b2c'] = [
                'result' => env('MPESA_B2C_RESULT_URL'),
                'timeout' => env('MPESA_B2C_TIMEOUT_URL'),
            ];
        }

        if (in_array('b2b', $apis)) {
            $callbacks['b2b'] = [
                'result' => env('MPESA_B2B_RESULT_URL'),
                'timeout' => env('MPESA_B2B_TIMEOUT_URL'),
            ];
        }

        if (in_array('transaction_status', $apis)) {
            $callbacks['status'] = [
                'result' => env('MPESA_STATUS_RESULT_URL'),
                'timeout' => env('MPESA_STATUS_TIMEOUT_URL'),
            ];
        }

        if (in_array('account_balance', $apis)) {
            $callbacks['balance'] = [
                'result' => env('MPESA_BALANCE_RESULT_URL'),
                'timeout' => env('MPESA_BALANCE_TIMEOUT_URL'),
            ];
        }

        if (in_array('reversal', $apis)) {
            $callbacks['reversal'] = [
                'result' => env('MPESA_REVERSAL_RESULT_URL'),
                'timeout' => env('MPESA_REVERSAL_TIMEOUT_URL'),
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
            'cache_ttl' => env('MPESA_SECURITY_CREDENTIAL_CACHE_TTL', 3600),
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
        $migrationPath = database_path("migrations/{$timestamp}_create_mpesa_accounts_table.php");

        File::copy(
            __DIR__.'/../../database/migrations/2024_01_01_000000_create_mpesa_accounts_table.php',
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
            $lines[] = '';
        }

        if (in_array('b2b', $this->selectedApis)) {
            $lines[] = '# B2B Configuration';
            $lines[] = 'MPESA_B2B_RESULT_URL=';
            $lines[] = 'MPESA_B2B_TIMEOUT_URL=';
            $lines[] = '';
        }

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
        if (is_array($value)) {
            $r = "[\n";
            $indent .= '    ';
            foreach ($value as $key => $val) {
                $r .= $indent;
                if (is_string($key) && preg_match('/^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$/', $key)) {
                    $r .= "'{$key}' => ";
                } elseif (is_string($key)) {
                    $r .= var_export($key, true).' => ';
                }
                $r .= $this->varExport($val, $indent).",\n";
            }
            $indent = substr($indent, 0, -4);

            return $r.$indent.']';
        }

        return var_export($value, true);
    }
}
