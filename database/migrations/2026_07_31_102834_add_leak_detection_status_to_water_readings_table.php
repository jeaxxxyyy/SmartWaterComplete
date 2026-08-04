<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn(
            'water_readings',
            'leak_detection_status'
        )) {
            Schema::table('water_readings', function (Blueprint $table) {
                $table->string(
                    'leak_detection_status',
                    30
                )->default('Normal');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(
            'water_readings',
            'leak_detection_status'
        )) {
            Schema::table('water_readings', function (Blueprint $table) {
                $table->dropColumn('leak_detection_status');
            });
        }
    }
};
