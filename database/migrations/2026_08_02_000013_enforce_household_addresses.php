<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The users table also stores administrator and device identities, so
        // address remains nullable for those roles. Household rows are
        // protected at the database boundary instead of forcing an
        // inapplicable address onto non-household accounts.
        $invalidHouseholds = DB::table('users')
            ->where('role', 'user')
            ->where(function ($query): void {
                $query->whereNull('address')->orWhereRaw("TRIM(address) = ''");
            })
            ->pluck('id');

        if ($invalidHouseholds->isNotEmpty()) {
            // Existing accounts are preserved and made visible for
            // administrative correction. The explicit review marker is not
            // presented as a real address and those accounts are suspended
            // until an administrator replaces it with the customer's address.
            DB::table('users')
                ->whereIn('id', $invalidHouseholds)
                ->update([
                    'address' => 'Address pending administrative review',
                    'is_active' => false,
                    'account_status' => 'suspended',
                ]);
        }

        // SQLite does not support adding a table constraint after creation;
        // application validation still protects the test/local SQLite path.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_household_address_required CHECK (role <> 'user' OR (address IS NOT NULL AND TRIM(address) <> ''))");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT users_household_address_required');
        }
    }
};
