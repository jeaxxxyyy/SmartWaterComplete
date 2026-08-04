<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('delivery_status')->default('delivered')->after('is_read')->index();
            $table->text('delivery_error')->nullable()->after('delivery_status');
            $table->timestamp('scheduled_at')->nullable()->after('sent_at')->index();
            $table->timestamp('archived_at')->nullable()->after('scheduled_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropColumn(['delivery_status', 'delivery_error', 'scheduled_at', 'archived_at']);
        });
    }
};
