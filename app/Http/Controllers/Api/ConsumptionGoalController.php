<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumptionGoal;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ConsumptionGoalController extends Controller
{
    public function show(Request $request)
    {
        abort_unless($request->user()?->isHousehold(), 403, 'Household access is required.');
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = isset($validated['month']) ? Carbon::createFromFormat('!Y-m', $validated['month']) : now();

        return response()->json(['data' => $this->payload($request->user()->id, $month)]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()?->isHousehold(), 403, 'Household access is required.');
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'target_liters' => ['required', 'numeric', 'min:1', 'max:100000000'],
        ]);
        $month = isset($validated['month']) ? Carbon::createFromFormat('!Y-m', $validated['month']) : now();
        ConsumptionGoal::query()->whereDate('month', $month->copy()->startOfMonth()->toDateString())->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['month' => $month->copy()->startOfMonth()->toDateString(), 'target_liters' => $validated['target_liters']],
        );

        return response()->json(['message' => 'Monthly goal saved.', 'data' => $this->payload($request->user()->id, $month)]);
    }

    private function payload(int $userId, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $goal = ConsumptionGoal::query()->where('user_id', $userId)->whereDate('month', $start)->first();
        $periodEnd = $start->isFuture() ? $start : (now()->lt($end) ? now() : $end);
        $used = $start->isFuture() ? 0 : (float) WaterReading::query()->where('user_id', $userId)->whereBetween('recorded_at', [$start, $periodEnd])->sum('liters_used');
        $elapsedDays = $start->isCurrentMonth() ? max(now()->day, 1) : $end->day;
        $forecast = $start->isFuture() ? 0 : ($used / $elapsedDays) * $end->day;
        $target = $goal ? (float) $goal->target_liters : null;

        return [
            'month' => $start->format('Y-m'),
            'target_liters' => $target,
            'used_liters' => round($used, 2),
            'forecast_liters' => round($forecast, 2),
            'remaining_liters' => $target === null ? null : round(max($target - $used, 0), 2),
            'progress_percent' => $target && $target > 0 ? round(($used / $target) * 100, 2) : null,
            'forecast_percent' => $target && $target > 0 ? round(($forecast / $target) * 100, 2) : null,
            'status' => $target === null ? 'not_set' : ($forecast > $target ? 'at_risk' : 'on_track'),
            'days_elapsed' => $elapsedDays,
            'days_in_month' => $end->day,
        ];
    }
}
