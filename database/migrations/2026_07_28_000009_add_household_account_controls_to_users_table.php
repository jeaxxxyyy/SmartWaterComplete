<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('created_by')
                ->nullable()
                ->after('created_by_admin')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('created_by');
            $table->string('account_status')->default('active')->after('approved_at')->index();
        });

        // Only accounts already marked as administrator-created are safely
        // classifiable. Unmarked household accounts remain unapproved and
        // cannot pass the household login gate.
        $adminId = DB::table('users')
            ->where('email', User::ADMIN_EMAIL)
            ->value('id');

        if ($adminId !== null) {
            DB::table('users')
                ->where('created_by_admin', true)
                ->update([
                    'created_by' => $adminId,
                    'approved_at' => DB::raw('created_at'),
                    'account_status' => 'active',
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['approved_at', 'account_status']);
        });
    }
};
