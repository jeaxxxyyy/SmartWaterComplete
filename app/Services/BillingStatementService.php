<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BillingStatement;
use App\Models\Billing;
use App\Models\User;
use App\Models\WaterReading;
use Carbon\Carbon;

class BillingStatementService
{
    public function generateForUser(User $user, ?Carbon $month = null): BillingStatement
    {
        $month ??= now()->startOfMonth();
        $periodStart = $month->copy()->startOfMonth();
        $periodEnd = $month->copy()->endOfMonth();

        $readings = WaterReading::query()
            ->where('user_id', $user->id)
            ->whereBetween('recorded_at', [$periodStart, $periodEnd]);

        $settings = Billing::query()
            ->where(function ($query) use ($user): void {
                $query->whereNull('user_id')
                    ->orWhere('user_id', $user->id);
            })
            ->where('is_active', true)
            ->latest('id')
            ->first();

        $totalLiters = (float) $readings->sum('liters_used');
        $totalCubicMeter = (float) $readings->sum('cubic_meter');
        $openingBalance = (float) BillingStatement::query()
            ->where('user_id', $user->id)
            ->where('billing_month', '<', $periodStart->toDateString())
            ->latest('billing_month')
            ->value('remaining_balance') ?? 0;

        $waterRate = (float) ($settings?->water_rate ?? 0);
        $subsidyDeduction = (float) ($settings?->subsidy_deduction ?? 0);
        $currentCharge = max(($totalCubicMeter * $waterRate) - $subsidyDeduction, 0);
        $amountPaid = 0;
        $remainingBalance = max($openingBalance + $currentCharge - $amountPaid, 0);

        return BillingStatement::updateOrCreate(
            [
                'user_id' => $user->id,
                'billing_month' => $periodStart->toDateString(),
            ],
            [
                'opening_balance' => $openingBalance,
                'total_liters' => $totalLiters,
                'total_cubic_meter' => $totalCubicMeter,
                'water_rate' => $waterRate,
                'subsidy_deduction' => $subsidyDeduction,
                'current_charge' => $currentCharge,
                'amount_paid' => $amountPaid,
                'remaining_balance' => $remainingBalance,
                'status' => $remainingBalance > 0 ? 'unpaid' : 'paid',
                'generated_at' => now(),
                'paid_at' => $remainingBalance <= 0 ? now() : null,
            ],
        );
    }

    public function recordAudit(?User $user, string $action, string $description, array $metadata = [], mixed $auditable = null): AuditLog
    {
        return AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'description' => $description,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->id,
            'metadata' => $metadata,
        ]);
    }
}
