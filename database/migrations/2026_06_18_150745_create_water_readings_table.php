<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('flow_rate', 10, 2);
            $table->decimal('liters_used', 12, 2);
            $table->decimal('cubic_meter', 12, 4);
            $table->decimal('estimated_bill', 12, 2)->default(0);
            $table->decimal('rate_per_cubic_meter', 12, 2)->default(0);
            $table->decimal('subsidy_deduction', 12, 2)->default(0);
            $table->decimal('threshold_liters', 12, 2)->nullable();
            $table->boolean('is_threshold_exceeded')->default(false);
            $table->boolean('is_possible_leak')->default(false);
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_readings');
    }
};
