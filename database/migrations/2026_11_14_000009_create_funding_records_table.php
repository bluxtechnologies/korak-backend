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
        Schema::create('funding_records', function (Blueprint $table) {
            $table->id();
            $table->string('ulid', 26)->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->string('provider', 50)->default('finitypay');
            $table->string('reference', 100)->unique();
            $table->string('provider_reference', 100)->nullable()->index();
            $table->unsignedBigInteger('amount'); // Minor units (kobo)
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 30)->default('pending')->index();
            $table->text('checkout_url')->nullable();
            $table->string('sender_name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funding_records');
    }
};
