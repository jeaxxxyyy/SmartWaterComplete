<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('water_readings', function (Blueprint $table): void {
            $table->foreignId('device_id')
                ->nullable()
                ->after('user_id')
                ->constrained('devices')
                ->nullOnDelete();
            $table->string('reading_id', 150)->nullable()->after('device_id');
            $table->unique(['device_id', 'reading_id'], 'water_readings_device_reading_unique');
            $table->index(['user_id', 'recorded_at'], 'water_readings_user_recorded_index');
        });
    }

    public function down(): void
    {
        Schema::table('water_readings', function (Blueprint $table): void {
            $table->dropUnique('water_readings_device_reading_unique');
            $table->dropIndex('water_readings_user_recorded_index');
            $table->dropConstrainedForeignId('device_id');
            $table->dropColumn('reading_id');
        });
    }
};
