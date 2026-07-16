<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('asset_number')->unique();
            $table->string('serial_number')->unique();
            $table->string('name');
            $table->foreignId('household_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('auth_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('hardware_id')->nullable()->unique();
            $table->string('firmware_version')->nullable();
            $table->string('status')->default('pending');
            $table->string('maintenance_status')->default('operational');
            $table->string('installation_address')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_telemetry_at')->nullable();
            $table->timestamp('calibrated_at')->nullable();
            $table->timestamp('calibration_due_at')->nullable();
            $table->timestamp('token_rotated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('activation_code_hash')->nullable();
            $table->timestamp('activation_expires_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
