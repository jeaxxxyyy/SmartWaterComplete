<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BillingStatement;
use App\Models\Billing;
use App\Models\User;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
            ->where(function ($query) use ($periodStart): void {
                $query->whereNull('effective_date')
                    ->orWhereDate('effective_date', '<=', $periodStart->toDateString());
            })
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN effective_date IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByDesc('effective_date')
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
        $fixedMonthlyCharge = (float) ($settings?->fixed_monthly_charge ?? 0);
        $subsidyDeduction = (float) ($settings?->subsidy_deduction ?? 0);
        $currentCharge = max(($totalCubicMeter * $waterRate) + $fixedMonthlyCharge - $subsidyDeduction, 0);
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
                'fixed_monthly_charge' => $fixedMonthlyCharge,
                'currency' => $settings?->currency ?? 'PHP',
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

    public function recordAudit(?User $user, string $action, string $description, array $metadata = [], mixed $auditable = null, string $outcome = 'success'): AuditLog
    {
        $request = app()->bound('request') ? request() : null;
        $safeMetadata = $this->sanitizeMetadata($metadata);
        $audit = AuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'description' => $description,
            'outcome' => $outcome,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => $request?->header('X-Request-ID'),
            'auditable_type' => is_object($auditable) ? $auditable::class : null,
            'auditable_id' => is_object($auditable) ? $auditable->id : null,
            'metadata' => $safeMetadata,
        ]);

        Log::channel('audit')->info($description, [
            'action' => $action,
            'outcome' => $outcome,
            'actor_id' => $user?->id,
            'auditable_type' => $audit->auditable_type,
            'auditable_id' => $audit->auditable_id,
            'request_id' => $audit->request_id,
        ]);

        return $audit;
    }

    private function sanitizeMetadata(array $metadata): array
    {
        $sensitiveKeys = [
            'password', 'password_confirmation', 'current_password', 'token', 'access_token',
            'activation_code', 'activation_code_hash', 'authorization', 'secret',
        ];

        $sanitize = function (mixed $value, ?string $key = null) use (&$sanitize, $sensitiveKeys): mixed {
            if ($key !== null && in_array(strtolower($key), $sensitiveKeys, true)) {
                return '[REDACTED]';
            }

            if (is_array($value)) {
                return collect($value)->mapWithKeys(fn ($item, $itemKey) => [$itemKey => $sanitize($item, (string) $itemKey)])->all();
            }

            if (is_string($value) && strlen($value) > 1000) {
                return substr($value, 0, 997).'...';
            }

            return $value;
        };

        return $sanitize($metadata);
    }
}
