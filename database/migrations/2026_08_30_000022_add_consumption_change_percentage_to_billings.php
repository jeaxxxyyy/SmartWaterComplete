<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billings', function (Blueprint $table): void {
            $table->decimal('consumption_change_threshold_percent', 8, 2)
                ->default(100)
                ->after('consumption_alert_enabled');
        });

        DB::table('billings')->update(['consumption_alert_unit' => 'percentage']);
    }

    public function down(): void
    {
        DB::table('billings')
            ->where('consumption_alert_unit', 'percentage')
            ->update(['consumption_alert_unit' => 'liters']);

        Schema::table('billings', function (Blueprint $table): void {
            $table->dropColumn('consumption_change_threshold_percent');
        });
    }
};
