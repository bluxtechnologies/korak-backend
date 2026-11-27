<?php

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
        Schema::create('website_services', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->string('category', 50)->default('general')->index();
            $table->text('description')->nullable();
            $table->string('icon', 100)->default('ti ti-apps');
            $table->boolean('is_active')->default(true)->index();
            $table->string('unavailable_headline', 150)->default('Service Unavailable');
            $table->text('maintenance_message')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // Seed initial default website services
        $now = now();
        DB::table('website_services')->insert([
            [
                'name' => 'Airtime VTU',
                'code' => 'airtime',
                'category' => 'telecom',
                'description' => 'Instant mobile airtime recharge across MTN, Airtel, Glo, and 9mobile networks.',
                'icon' => 'ti ti-device-mobile',
                'is_active' => true,
                'unavailable_headline' => 'Airtime Service Unavailable',
                'maintenance_message' => 'Airtime recharge is currently undergoing scheduled maintenance. Please check back shortly.',
                'sort_order' => 1,
                'metadata' => json_encode(['provider' => 'finitypay']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Mobile Data Bundles',
                'code' => 'data',
                'category' => 'telecom',
                'description' => 'High-speed mobile data bundles for personal, business, and SME subscribers.',
                'icon' => 'ti ti-wifi',
                'is_active' => true,
                'unavailable_headline' => 'Mobile Data Service Unavailable',
                'maintenance_message' => 'Mobile data purchasing is currently unavailable due to maintenance. Please check back shortly.',
                'sort_order' => 2,
                'metadata' => json_encode(['provider' => 'finitypay']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Cable TV Subscription',
                'code' => 'cable_tv',
                'category' => 'entertainment',
                'description' => 'Instant subscription renewals and package upgrades for DStv, GOtv, Startimes, and Showmax.',
                'icon' => 'ti ti-device-tv',
                'is_active' => true,
                'unavailable_headline' => 'Cable TV Service Unavailable',
                'maintenance_message' => 'Cable TV subscription renewal is temporarily unavailable. Please check back shortly.',
                'sort_order' => 3,
                'metadata' => json_encode(['provider' => 'finitypay']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Electricity Bills',
                'code' => 'electricity',
                'category' => 'utilities',
                'description' => 'Prepaid meter token generation and postpaid utility bill payments for all Nigerian DisCos.',
                'icon' => 'ti ti-bolt',
                'is_active' => true,
                'unavailable_headline' => 'Electricity Token Service Unavailable',
                'maintenance_message' => 'Electricity meter token generation is temporarily offline. Please check back shortly.',
                'sort_order' => 4,
                'metadata' => json_encode(['provider' => 'finitypay']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Sports Betting Wallet',
                'code' => 'betting',
                'category' => 'entertainment',
                'description' => 'Direct betting account funding for Bet9ja, SportyBet, 1xBet, BangBet, and top platforms.',
                'icon' => 'ti ti-ball-football',
                'is_active' => true,
                'unavailable_headline' => 'Betting Top-up Service Unavailable',
                'maintenance_message' => 'Betting wallet funding is temporarily disabled for maintenance. Please check back shortly.',
                'sort_order' => 5,
                'metadata' => json_encode(['provider' => 'finitypay']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Education & Result Pins',
                'code' => 'education',
                'category' => 'education',
                'description' => 'Instant exam result checker PINs and registration scratch cards for WAEC, NECO, JAMB, and NABTEB.',
                'icon' => 'ti ti-school',
                'is_active' => true,
                'unavailable_headline' => 'Education Pin Service Unavailable',
                'maintenance_message' => 'Education exam checker PIN purchase is currently unavailable. Please check back shortly.',
                'sort_order' => 6,
                'metadata' => json_encode(['provider' => 'finitypay']),
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_services');
    }
};
