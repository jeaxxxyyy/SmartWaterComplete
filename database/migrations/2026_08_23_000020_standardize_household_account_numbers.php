<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $households = DB::table('users')
            ->where('role', 'user')
            ->whereNotNull('account_number')
            ->orderBy('id')
            ->get(['id', 'account_number']);

        $occupied = DB::table('users')
            ->whereNotNull('account_number')
            ->pluck('account_number')
            ->flip()
            ->all();

        $highestNumber = $households->reduce(function (int $highest, object $household): int {
            preg_match('/^ACC-USER-(\d+)$/', (string) $household->account_number, $matches);

            return max($highest, isset($matches[1]) ? (int) $matches[1] : 0);
        }, 0);

        foreach ($households as $household) {
            preg_match('/^ACC-USER-(\d+)$/', (string) $household->account_number, $matches);
            if (! isset($matches[1])) {
                continue;
            }

            $candidate = sprintf('ACC-USER-%04d', (int) $matches[1]);
            if ($candidate === $household->account_number) {
                continue;
            }

            while (isset($occupied[$candidate]) && $candidate !== $household->account_number) {
                $highestNumber++;
                $candidate = sprintf('ACC-USER-%04d', $highestNumber);
            }

            DB::table('users')->where('id', $household->id)->update([
                'account_number' => $candidate,
            ]);

            unset($occupied[$household->account_number]);
            $occupied[$candidate] = true;
        }
    }

    public function down(): void
    {
        // Canonical account identifiers are intentionally not made ambiguous again.
    }
};
