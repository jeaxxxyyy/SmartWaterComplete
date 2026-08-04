<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BillingStatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function __construct(protected BillingStatementService $audit) {}

    public function show(Request $request)
    {
        $user = $this->householdUser($request);

        return response()->json(['data' => $this->profile($user)]);
    }

    public function update(Request $request)
    {
        $user = $this->householdUser($request);
        $validated = $request->validate([
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
        ]);
        $user->update($validated);
        $this->audit->recordAudit($user, 'account_updated', 'Household contact details were updated.', [
            'fields' => array_keys($validated),
        ], $user);

        return response()->json(['message' => 'Account updated.', 'data' => $this->profile($user->fresh())]);
    }

    public function password(Request $request)
    {
        $this->householdUser($request);
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        if (! Hash::check($validated['current_password'], $request->user()->password)) {
            $this->audit->recordAudit($request->user(), 'password_change_failed', 'A password change was rejected because the current password was incorrect.', [], $request->user(), 'failure');
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }
        $request->user()->update(['password' => $validated['password']]);
        $this->audit->recordAudit($request->user(), 'password_changed', 'Account password was changed.', [], $request->user());

        return response()->json(['message' => 'Password changed successfully.']);
    }

    private function householdUser(Request $request)
    {
        abort_unless($request->user()?->isHousehold() && $request->user()->isAccountActive(), 403, 'Household account access is required.');

        return $request->user();
    }

    private function profile($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'account_number' => $user->account_number,
            'address' => $user->address,
            'phone' => $user->phone,
            'role' => $user->role,
            'is_active' => (bool) $user->is_active,
            'account_status' => $user->account_status ?? 'active',
        ];
    }
}
