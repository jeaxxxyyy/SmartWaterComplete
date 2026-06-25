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
        Schema::create('billings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('water_rate', 12, 2)->default(0);
            $table->decimal('subsidy_deduction', 12, 2)->default(0);
            $table->decimal('threshold_liters', 12, 2)->default(0);
            $table->decimal('leak_flow_rate_threshold', 12, 2)->default(0);
            $table->unsignedInteger('quiet_hours_start')->default(22);
            $table->unsignedInteger('quiet_hours_end')->default(4);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billings');
    }
};
