<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use App\Services\BillingStatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    public function __construct(protected BillingStatementService $audit) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(['pending', 'active', 'suspended', 'maintenance', 'decommissioned'])],
            'household_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $devices = Device::query()
            ->with(['household:id,name,email,account_number,address', 'authUser:id,email'])
            ->when(! empty($validated['search']), function ($query) use ($validated): void {
                $search = '%'.$validated['search'].'%';
                $query->where(fn ($builder) => $builder->where('asset_number', 'like', $search)
                    ->orWhere('serial_number', 'like', $search)
                    ->orWhere('name', 'like', $search)
                    ->orWhere('hardware_id', 'like', $search));
            })
            ->when(! empty($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->when(! empty($validated['household_id']), fn ($query) => $query->where('household_id', $validated['household_id']))
            ->latest()->get()
            ->each(fn (Device $device) => $device->setAttribute('active_token_count', $device->authUser->tokens()->count()));

        return response()->json(['data' => $devices]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'household_id' => ['required', 'integer', 'exists:users,id'],
            'asset_number' => ['required', 'string', 'max:100', 'unique:devices,asset_number'],
            'serial_number' => ['required', 'string', 'max:150', 'unique:devices,serial_number'],
            'name' => ['required', 'string', 'max:150'],
            'installation_address' => ['nullable', 'string', 'max:255'],
            'installed_at' => ['nullable', 'date'],
            'calibrated_at' => ['nullable', 'date'],
            'calibration_due_at' => ['nullable', 'date', 'after_or_equal:calibrated_at'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $household = User::query()->whereKey($validated['household_id'])->where('role', 'user')->firstOrFail();

        [$device, $code] = DB::transaction(function () use ($validated, $household): array {
            $uuid = (string) Str::uuid();
            $authUser = User::create([
                'name' => $validated['name'].' Device Identity',
                'email' => $uuid.'@devices.smartwater.local',
                'password' => Str::random(64),
                'role' => 'device',
                'assigned_user_id' => $household->id,
                'account_number' => 'DEV-'.strtoupper(substr(str_replace('-', '', $uuid), 0, 12)),
                'is_active' => true,
                'created_by_admin' => true,
                'email_verified_at' => now(),
            ]);
            $device = Device::create([
                ...$validated,
                'uuid' => $uuid,
                'auth_user_id' => $authUser->id,
                'status' => 'pending',
            ]);
            $code = $this->setActivationCode($device);

            return [$device, $code];
        });

        $this->audit->recordAudit($request->user(), 'device_registered', 'Registered and assigned a municipal water meter.', [
            'device_uuid' => $device->uuid, 'household_id' => $household->id, 'asset_number' => $device->asset_number,
        ], $device);

        return response()->json([
            'message' => 'Device registered. The activation code is shown once and expires shortly.',
            'data' => ['device' => $device->load('household'), 'activation_code' => $code, 'expires_at' => $device->activation_expires_at],
        ], 201);
    }

    public function update(Request $request, Device $device)
    {
        $validated = $request->validate([
            'household_id' => ['sometimes', 'integer', 'exists:users,id'],
            'name' => ['sometimes', 'string', 'max:150'],
            'installation_address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'suspended', 'maintenance'])],
            'maintenance_status' => ['sometimes', Rule::in(['operational', 'inspection_due', 'under_maintenance', 'faulty'])],
            'calibrated_at' => ['sometimes', 'nullable', 'date'],
            'calibration_due_at' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        if (isset($validated['household_id'])) {
            User::query()->whereKey($validated['household_id'])->where('role', 'user')->firstOrFail();
            $device->authUser->update(['assigned_user_id' => $validated['household_id']]);
        }
        if (($validated['status'] ?? null) === 'suspended') {
            $device->authUser->tokens()->delete();
            $validated['revoked_at'] = now();
        }
        $before = $device->only(array_keys($validated));
        $device->update($validated);
        $this->audit->recordAudit($request->user(), 'device_updated', 'Updated a municipal water meter.', [
            'before' => $before, 'after' => $device->only(array_keys($validated)),
        ], $device);

        return response()->json(['message' => 'Device updated successfully.', 'data' => $device->fresh()->load('household')]);
    }

    public function activationCode(Request $request, Device $device)
    {
        if ($device->status === 'decommissioned') {
            return response()->json(['message' => 'A decommissioned device cannot be activated.'], 422);
        }
        $device->authUser->tokens()->delete();
        $code = $this->setActivationCode($device, true);
        $this->audit->recordAudit($request->user(), 'device_activation_code_issued', 'Issued a one-time device activation code.', [], $device);

        return response()->json(['message' => 'Activation code issued. It is shown once.', 'data' => [
            'device_uuid' => $device->uuid, 'activation_code' => $code, 'expires_at' => $device->activation_expires_at,
        ]]);
    }

    public function activate(Request $request)
    {
        $validated = $request->validate([
            'device_uuid' => ['required', 'uuid'],
            'activation_code' => ['required', 'string', 'size:8'],
            'hardware_id' => ['required', 'string', 'max:150'],
            'firmware_version' => ['required', 'string', 'max:50'],
        ]);
        $device = Device::query()->where('uuid', $validated['device_uuid'])->first();
        if (! $device || ! $device->activation_code_hash || ! $device->activation_expires_at?->isFuture()
            || ! Hash::check(strtoupper($validated['activation_code']), $device->activation_code_hash)) {
            return response()->json(['message' => 'The activation details are invalid or expired.'], 422);
        }
        if ($device->hardware_id && $device->hardware_id !== $validated['hardware_id']) {
            return response()->json(['message' => 'This meter is already bound to different hardware.'], 409);
        }

        $device->authUser->tokens()->delete();
        $expiresAt = now()->addDays(config('devices.token_days'));
        $token = $device->authUser->createToken('meter-'.$device->asset_number, ['device-upload'], $expiresAt)->plainTextToken;
        $device->update([
            'hardware_id' => $validated['hardware_id'], 'firmware_version' => $validated['firmware_version'],
            'status' => 'active', 'activated_at' => now(), 'last_seen_at' => now(),
            'token_rotated_at' => now(), 'revoked_at' => null, 'activation_code_hash' => null,
            'activation_expires_at' => null,
        ]);
        $this->audit->recordAudit(null, 'device_activated', 'A municipal water meter completed secure activation.', [
            'hardware_id' => $validated['hardware_id'],
            'firmware_version' => $validated['firmware_version'],
        ], $device);

        return response()->json(['message' => 'Device activated successfully.', 'data' => [
            'token' => $token, 'token_expires_at' => $expiresAt, 'upload_interval_seconds' => 3,
            'household_account' => $device->household->account_number,
        ]]);
    }

    public function decommission(Request $request, Device $device)
    {
        $device->authUser->tokens()->delete();
        $device->update(['status' => 'decommissioned', 'revoked_at' => now(), 'activation_code_hash' => null, 'activation_expires_at' => null]);
        $this->audit->recordAudit($request->user(), 'device_decommissioned', 'Decommissioned a municipal water meter and revoked its credentials.', [], $device);

        return response()->json(['message' => 'Device decommissioned and credentials revoked.']);
    }

    private function setActivationCode(Device $device, bool $resetHardware = false): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = collect(range(1, 8))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
        $device->update([
            'activation_code_hash' => Hash::make($code),
            'activation_expires_at' => now()->addMinutes(config('devices.activation_minutes')),
            'status' => 'pending',
            ...($resetHardware ? ['hardware_id' => null, 'revoked_at' => now()] : []),
        ]);

        return $code;
    }
}
