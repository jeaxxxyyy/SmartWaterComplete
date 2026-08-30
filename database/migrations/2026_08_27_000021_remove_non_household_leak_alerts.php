<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('leak_alerts')
            ->whereIn('user_id', function ($query): void {
                $query->select('id')
                    ->from('users')
                    ->where('role', '<>', 'user');
            })
            ->delete();
    }

    public function down(): void
    {
        // This only removes invalid derived alerts and cannot be restored safely.
    }
};
