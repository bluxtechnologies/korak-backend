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
        Schema::create('bill_orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('reference', 64)->unique();
            $table->string('provider_reference', 128)->nullable()->index();
            $table->string('category', 64)->index();
            $table->string('service_code', 64)->index();
            $table->string('service_name', 128);
            $table->string('package_code', 64)->nullable()->index();
            $table->string('customer_identifier', 128)->index();
            $table->string('customer_name', 128)->nullable();
            $table->unsignedBigInteger('amount'); // Charged amount in kobo
            $table->unsignedBigInteger('face_amount'); // Face value in kobo
            $table->unsignedBigInteger('discount_amount')->default(0); // Discount in kobo
            $table->char('currency', 3)->default('NGN');
            $table->string('status', 32)->default('pending')->index();
            $table->string('token', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bill_orders');
    }
};
