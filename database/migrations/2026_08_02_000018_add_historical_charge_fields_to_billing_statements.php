<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_statements', function (Blueprint $table): void {
            $table->decimal('fixed_monthly_charge', 12, 2)->default(0)->after('water_rate');
            $table->string('currency', 3)->default('PHP')->after('fixed_monthly_charge');
        });
    }

    public function down(): void
    {
        Schema::table('billing_statements', function (Blueprint $table): void {
            $table->dropColumn(['fixed_monthly_charge', 'currency']);
        });
    }
};
