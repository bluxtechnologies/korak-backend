<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transaction_fee_tiers', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_type', 30)->default('deposit')->index();
            $table->string('name', 100);
            $table->unsignedBigInteger('min_amount'); // Minor units (kobo), e.g. 100000 = ₦1,000.00
            $table->unsignedBigInteger('max_amount')->nullable(); // Nullable for top open-ended tier
            $table->unsignedBigInteger('fee'); // Minor units (kobo), e.g. 7500 = ₦75.00
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
        });

        // Seed initial standard deposit fee tiers per business specifications
        $defaultTiers = [
            [
                'transaction_type' => 'deposit',
                'name' => 'Below ₦1,000',
                'min_amount' => 0,
                'max_amount' => 99999, // ₦0.00 - ₦999.99
                'fee' => 2000, // ₦20.00
                'is_active' => true,
                'priority' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'transaction_type' => 'deposit',
                'name' => '₦1,000 to ₦10,000',
                'min_amount' => 100000, // ₦1,000.00
                'max_amount' => 1000000, // ₦10,000.00
                'fee' => 7500, // ₦75.00
                'is_active' => true,
                'priority' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'transaction_type' => 'deposit',
                'name' => '₦10,001 to ₦50,000',
                'min_amount' => 1000001, // ₦10,000.01
                'max_amount' => 5000000, // ₦50,000.00
                'fee' => 15500, // ₦155.00
                'is_active' => true,
                'priority' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'transaction_type' => 'deposit',
                'name' => '₦50,001 to ₦100,000',
                'min_amount' => 5000001, // ₦50,000.01
                'max_amount' => 10000000, // ₦100,000.00
                'fee' => 35000, // ₦350.00
                'is_active' => true,
                'priority' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'transaction_type' => 'deposit',
                'name' => 'Above ₦100,000',
                'min_amount' => 10000001, // ₦100,000.01+
                'max_amount' => null, // Open-ended
                'fee' => 50000, // ₦500.00
                'is_active' => true,
                'priority' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('transaction_fee_tiers')->insert($defaultTiers);

        // Synchronize initial configuration into settings table
        Setting::setValue('deposit_fee_tiers', array_map(function (array $tier): array {
            return [
                'name' => $tier['name'],
                'min_amount' => $tier['min_amount'],
                'max_amount' => $tier['max_amount'],
                'fee' => $tier['fee'],
                'min_amount_formatted' => '₦'.number_format($tier['min_amount'] / 100, 2),
                'max_amount_formatted' => $tier['max_amount'] !== null ? '₦'.number_format($tier['max_amount'] / 100, 2) : 'No limit',
                'fee_formatted' => '₦'.number_format($tier['fee'] / 100, 2),
                'is_active' => true,
            ];
        }, $defaultTiers));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Setting::forgetValue('deposit_fee_tiers');
        Schema::dropIfExists('transaction_fee_tiers');
    }
};
