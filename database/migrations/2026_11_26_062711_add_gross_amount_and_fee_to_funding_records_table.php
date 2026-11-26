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
        Schema::table('funding_records', function (Blueprint $table): void {
            $table->unsignedBigInteger('gross_amount')->nullable()->after('provider_reference'); // Gross amount paid/received
            $table->unsignedBigInteger('fee')->default(0)->after('gross_amount'); // Fee charged in kobo
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funding_records', function (Blueprint $table): void {
            $table->dropColumn(['gross_amount', 'fee']);
        });
    }
};
