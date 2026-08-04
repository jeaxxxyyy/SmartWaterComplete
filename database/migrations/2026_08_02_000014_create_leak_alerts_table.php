<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leak_alerts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('first_water_reading_id')->nullable()->constrained('water_readings')->nullOnDelete();
            $table->foreignId('latest_water_reading_id')->nullable()->constrained('water_readings')->nullOnDelete();
            $table->foreignId('notification_id')->nullable()->constrained('notifications')->nullOnDelete();
            $table->string('status')->default('detected')->index();
            $table->text('notes')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('last_detected_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['detected_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leak_alerts');
    }
};
