<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_payments', function (Blueprint $table): void {
            $table->string('status')->default('confirmed')->after('payment_method');
            $table->string('proof_path')->nullable()->after('reference_number');
            $table->foreignId('verified_by')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('received_at');
            $table->foreignId('voided_by')->nullable()->after('verified_by')->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable()->after('verified_at');
            $table->text('status_note')->nullable()->after('notes');
            $table->index(['billing_statement_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('billing_payments', function (Blueprint $table): void {
            $table->dropIndex(['billing_statement_id', 'status']);
            $table->dropConstrainedForeignId('voided_by');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['status', 'proof_path', 'verified_at', 'voided_at', 'status_note']);
        });
    }
};
