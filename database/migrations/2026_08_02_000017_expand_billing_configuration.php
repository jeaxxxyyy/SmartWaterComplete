<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billings', function (Blueprint $table): void {
            $table->decimal('fixed_monthly_charge', 12, 2)->default(0)->after('water_rate');
            $table->string('currency', 3)->default('PHP')->after('subsidy_deduction');
            $table->date('effective_date')->nullable()->after('currency');
            $table->boolean('consumption_alert_enabled')->default(true)->after('threshold_liters');
            $table->string('consumption_alert_unit', 30)->default('liters')->after('consumption_alert_enabled');
            $table->string('consumption_alert_behavior', 40)->default('household')->after('consumption_alert_unit');
            $table->boolean('leak_alert_enabled')->default(true)->after('leak_flow_rate_threshold');
            $table->unsignedInteger('leak_continuous_duration_minutes')->default(0)->after('leak_alert_enabled');
            $table->string('leak_alert_behavior', 40)->default('household')->after('leak_continuous_duration_minutes');
            $table->index(['is_active', 'effective_date']);
        });
    }

    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table): void {
            $table->dropIndex(['is_active', 'effective_date']);
            $table->dropColumn([
                'fixed_monthly_charge',
                'currency',
                'effective_date',
                'consumption_alert_enabled',
                'consumption_alert_unit',
                'consumption_alert_behavior',
                'leak_alert_enabled',
                'leak_continuous_duration_minutes',
                'leak_alert_behavior',
            ]);
        });
    }
};
