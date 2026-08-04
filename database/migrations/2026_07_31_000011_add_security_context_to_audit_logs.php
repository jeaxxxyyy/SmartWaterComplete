<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('outcome')->default('success')->after('description')->index();
            $table->string('ip_address', 45)->nullable()->after('outcome');
            $table->text('user_agent')->nullable()->after('ip_address');
            $table->string('request_id', 100)->nullable()->after('user_agent')->index();
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['action', 'created_at']);
            $table->dropColumn(['outcome', 'ip_address', 'user_agent', 'request_id']);
        });
    }
};
