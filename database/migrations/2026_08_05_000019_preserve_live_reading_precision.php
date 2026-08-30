<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('water_readings', function (Blueprint $table): void {
            $table->decimal('liters_used', 12, 6)->change();
            $table->decimal('cubic_meter', 12, 8)->change();
            $table->index(['device_id', 'recorded_at'], 'water_readings_device_recorded_index');
        });
    }

    public function down(): void
    {
        Schema::table('water_readings', function (Blueprint $table): void {
            $table->dropIndex('water_readings_device_recorded_index');
            $table->decimal('liters_used', 12, 2)->change();
            $table->decimal('cubic_meter', 12, 4)->change();
        });
    }
};
