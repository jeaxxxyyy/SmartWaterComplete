<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\HouseholdConsumptionService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HouseholdConsumptionController extends Controller
{
    public function __construct(protected HouseholdConsumptionService $consumption) {}

    public function show(Request $request)
    {
        $validated = $request->validate([
            'period' => ['required', Rule::in(['daily', 'weekly', 'monthly'])],
        ]);

        return response()->json([
            'data' => $this->consumption->forPeriod($request->user(), $validated['period']),
        ]);
    }
}
