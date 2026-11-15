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
        Schema::create('funding_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_reference', 100)->unique();
            $table->string('provider', 50)->default('finitypay');
            $table->string('event_type', 50)->index();
            $table->json('payload');
            $table->string('status', 30)->default('received')->index();
            $table->text('failure_reason')->nullable();
            $table->foreignId('funding_record_id')->nullable()->constrained('funding_records')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funding_webhook_events');
    }
};
