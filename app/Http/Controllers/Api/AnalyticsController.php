<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AnalyticsController extends Controller
{
    public function __construct(protected AnalyticsService $analytics) {}

    public function admin(Request $request)
    {
        [$from, $to, $range] = $this->resolveRange($request, 'last_12_months');

        return response()->json([
            'data' => [
                ...$this->analytics->admin($from, $to),
                'range' => $range,
            ],
        ]);
    }

    public function household(Request $request)
    {
        [$from, $to, $range] = $this->resolveRange($request, 'last_30_days');

        return response()->json([
            'data' => [
                ...$this->analytics->household($request->user(), $from, $to),
                'range' => $range,
            ],
        ]);
    }

    private function resolveRange(Request $request, string $default): array
    {
        $validated = $request->validate([
            'range' => ['nullable', 'in:last_7_days,last_30_days,last_3_months,last_12_months,custom'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $range = $validated['range'] ?? (($validated['from'] ?? null) || ($validated['to'] ?? null) ? 'custom' : $default);
        $timezone = config('app.timezone');

        if ($range === 'custom') {
            if (empty($validated['from']) || empty($validated['to'])) {
                throw ValidationException::withMessages(['range' => 'Both start and end dates are required for a custom range.']);
            }

            $from = Carbon::parse($validated['from'], $timezone)->startOfDay();
            $to = Carbon::parse($validated['to'], $timezone)->endOfDay();
        } else {
            $to = Carbon::now($timezone)->endOfDay();
            $from = match ($range) {
                'last_7_days' => $to->copy()->subDays(6)->startOfDay(),
                'last_30_days' => $to->copy()->subDays(29)->startOfDay(),
                'last_3_months' => $to->copy()->subMonths(3)->startOfDay(),
                'last_12_months' => $to->copy()->subMonths(12)->startOfDay(),
            };
        }

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        }

        if ($from->diffInDays($to) > 366) {
            throw ValidationException::withMessages(['range' => 'The selected range cannot exceed 366 days.']);
        }

        return [$from, $to, $range];
    }
}
