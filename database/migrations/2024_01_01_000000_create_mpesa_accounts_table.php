<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mpesa_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Acme Corp M-Pesa Account"
            $table->string('tenant_id')->nullable()->index(); // For multi-tenancy
            $table->json('credentials'); // Stores all M-Pesa configuration
            $table->boolean('is_active')->default(true);
            $table->string('environment')->default('sandbox'); // sandbox|production
            $table->timestamps();
            $table->softDeletes();

            // If using multi-tenancy, ensure one active account per tenant
            $table->unique(['tenant_id', 'is_active'], 'unique_active_account_per_tenant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mpesa_accounts');
    }
};
