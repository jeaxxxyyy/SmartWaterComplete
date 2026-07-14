<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('water_readings', function (Blueprint $table): void {
            $table->string('device_name')->nullable()->after('user_id');
            $table->json('device_payload')->nullable()->after('device_name');
            $table->string('leak_detection_status')->nullable()->after('device_payload');
            $table->decimal('water_limit_liters', 12, 2)->nullable()->after('threshold_liters');
            $table->string('monitoring_status')->default('online')->after('water_limit_liters');
            $table->unsignedTinyInteger('wifi_signal_strength')->nullable()->after('monitoring_status');
            $table->string('device_connection_status')->default('connected')->after('wifi_signal_strength');
            $table->string('server_connection_status')->default('connected')->after('device_connection_status');
        });
    }

    public function down(): void
    {
        Schema::table('water_readings', function (Blueprint $table): void {
            $table->dropColumn([
                'device_name',
                'device_payload',
                'leak_detection_status',
                'water_limit_liters',
                'monitoring_status',
                'wifi_signal_strength',
                'device_connection_status',
                'server_connection_status',
            ]);
        });
    }
};
