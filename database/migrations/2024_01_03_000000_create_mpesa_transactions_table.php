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
        Schema::create('mpesa_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type')->index(); // stk_push, c2b, b2c, etc.
            $table->string('idempotency_key')->nullable()->unique();
            $table->string('merchant_request_id')->nullable()->index();
            $table->string('checkout_request_id')->nullable()->index();
            $table->string('conversation_id')->nullable()->index();
            $table->string('originator_conversation_id')->nullable()->index();
            $table->string('transaction_id')->nullable()->index(); // M-Pesa Receipt Number

            // Request Details
            $table->string('party_a')->nullable(); // Sender/Phone
            $table->string('party_b')->nullable(); // Receiver/Shortcode
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('account_reference')->nullable();
            $table->string('transaction_desc')->nullable();
            $table->string('remarks')->nullable();

            // Status
            $table->string('status')->default('PENDING')->index(); // PENDING, SUCCESS, FAILED
            $table->string('result_code')->nullable();
            $table->string('result_desc')->nullable();

            // Payloads
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('callback_payload')->nullable();

            $table->timestamps();

            // Composite indexes
            $table->index(['transaction_type', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mpesa_transactions');
    }
};
