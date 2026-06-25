<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaterReading;
use App\Services\WaterConsumptionService;
use Illuminate\Http\Request;

class WaterController extends Controller
{
    public function __construct(
        protected WaterConsumptionService $waterConsumptionService
    ) {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = WaterReading::with(['notifications', 'user'])
            ->latest('recorded_at')
            ->latest('id');

        if ($request->user()?->isAdmin() !== true) {
            $query->where('user_id', $request->user()?->id);
        } elseif (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        if (! empty($validated['from'])) {
            $query->whereDate('recorded_at', '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->whereDate('recorded_at', '<=', $validated['to']);
        }

        return $query
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();
    }

    public function store(Request $request)
    {
        $accessToken = $request->user()?->currentAccessToken();

        if ($accessToken === null || ! $accessToken->can('device-upload')) {
            return response()->json([
                'message' => 'Device access is required.',
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'flow_rate' => ['required', 'numeric', 'min:0'],
            'liters_used' => ['required', 'numeric', 'min:0'],
            'cubic_meter' => ['nullable', 'numeric', 'min:0'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        if ((int) $request->user()->id !== (int) $validated['user_id']) {
            return response()->json([
                'message' => 'You may only submit readings for the authenticated device account.',
            ], 403);
        }

        $validated['cubic_meter'] = $validated['cubic_meter'] ?? ($validated['liters_used'] / 1000);

        $result = $this->waterConsumptionService->storeReading($validated);

        return response()->json([
            'message' => 'Water data saved successfully',
            'data' => [
                'reading' => $result['reading'],
                'notifications' => $result['notifications'],
            ],
        ], 201);
    }
}
