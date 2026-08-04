<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->index(['role', 'is_active', 'account_status'], 'users_role_access_index');
        });

        Schema::table('notifications', function (Blueprint $table): void {
            $table->index(['user_id', 'is_read', 'created_at'], 'notifications_user_read_created_index');
            $table->index(['type', 'created_at'], 'notifications_type_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_user_read_created_index');
            $table->dropIndex('notifications_type_created_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_role_access_index');
        });
    }
};
