<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request)
    {
        abort_unless($request->user()?->isHousehold(), 403, 'Household access is required.');

        return response()->json(['data' => $this->payload($request)]);
    }

    public function update(Request $request)
    {
        abort_unless($request->user()?->isHousehold(), 403, 'Household access is required.');
        $validated = $request->validate([
            'announcements' => ['required', 'boolean'],
            'billing_updates' => ['required', 'boolean'],
            'consumption_alerts' => ['required', 'boolean'],
            'leak_alerts' => ['required', 'boolean'],
            'service_request_updates' => ['required', 'boolean'],
            'quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['nullable', 'date_format:H:i'],
            'preferred_locale' => ['required', Rule::in(['en', 'fil'])],
            'low_bandwidth_mode' => ['required', 'boolean'],
        ]);
        $userSettings = collect($validated)->only(['preferred_locale', 'low_bandwidth_mode'])->all();
        $preferenceSettings = collect($validated)->except(['preferred_locale', 'low_bandwidth_mode'])->all();
        $request->user()->update($userSettings);
        NotificationPreference::updateOrCreate(['user_id' => $request->user()->id], $preferenceSettings);

        return response()->json(['message' => 'Preferences saved.', 'data' => $this->payload($request)]);
    }

    private function payload(Request $request): array
    {
        $preference = NotificationPreference::firstOrCreate(['user_id' => $request->user()->id]);

        return [
            ...$preference->only(['announcements', 'billing_updates', 'consumption_alerts', 'leak_alerts', 'service_request_updates', 'quiet_hours_start', 'quiet_hours_end']),
            'preferred_locale' => $request->user()->preferred_locale ?? 'en',
            'low_bandwidth_mode' => (bool) $request->user()->low_bandwidth_mode,
        ];
    }
}
