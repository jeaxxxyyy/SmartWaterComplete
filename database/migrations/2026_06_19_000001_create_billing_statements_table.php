<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('billing_month');
            $table->decimal('opening_balance', 12, 2)->default(0);
            $table->decimal('total_liters', 12, 2)->default(0);
            $table->decimal('total_cubic_meter', 12, 4)->default(0);
            $table->decimal('water_rate', 12, 2)->default(0);
            $table->decimal('subsidy_deduction', 12, 2)->default(0);
            $table->decimal('current_charge', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('remaining_balance', 12, 2)->default(0);
            $table->string('status')->default('unpaid');
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_statements');
    }
};
