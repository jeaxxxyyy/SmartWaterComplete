<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('preferred_locale', 5)->default('en')->after('phone');
            $table->boolean('low_bandwidth_mode')->default(false)->after('preferred_locale');
        });

        Schema::table('billing_statements', function (Blueprint $table): void {
            $table->string('statement_number')->nullable()->unique()->after('id');
            $table->date('due_date')->nullable()->after('billing_month');
        });
        DB::table('billing_statements')->orderBy('id')->get(['id', 'billing_month'])->each(function ($statement): void {
            $month = Carbon::parse($statement->billing_month)->startOfMonth();
            DB::table('billing_statements')->where('id', $statement->id)->update([
                'statement_number' => sprintf('SW-%s-%06d', $month->format('Ym'), $statement->id),
                'due_date' => $month->copy()->endOfMonth()->addDays(15)->toDateString(),
            ]);
        });

        Schema::create('billing_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('billing_statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('receipt_number')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('payment_method')->default('cash');
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();
            $table->index(['billing_statement_id', 'received_at']);
        });

        Schema::create('service_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('reference_number')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('billing_statement_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category');
            $table->string('subject');
            $table->text('description');
            $table->string('priority')->default('normal');
            $table->string('status')->default('open');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('service_request_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('service_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('announcements')->default(true);
            $table->boolean('billing_updates')->default(true);
            $table->boolean('consumption_alerts')->default(true);
            $table->boolean('leak_alerts')->default(true);
            $table->boolean('service_request_updates')->default(true);
            $table->time('quiet_hours_start')->nullable();
            $table->time('quiet_hours_end')->nullable();
            $table->timestamps();
        });

        Schema::create('consumption_goals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->decimal('target_liters', 12, 2);
            $table->timestamps();
            $table->unique(['user_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_goals');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('service_request_messages');
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('billing_payments');

        Schema::table('billing_statements', function (Blueprint $table): void {
            $table->dropUnique(['statement_number']);
            $table->dropColumn(['statement_number', 'due_date']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['preferred_locale', 'low_bandwidth_mode']);
        });
    }
};
