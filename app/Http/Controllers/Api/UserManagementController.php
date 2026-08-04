<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BillingStatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function __construct(
        protected BillingStatementService $billingStatementService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['user', 'admin', 'device'])],
            'is_active' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = $this->userQuery($validated)
            ->when(! empty($validated['role']), fn ($query) => $query->where('role', $validated['role']))
            ->latest()
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();

        return response()->json([
            'data' => $users,
        ]);
    }

    public function indexHouseholds(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'account_status' => ['nullable', Rule::in([
                User::ACCOUNT_STATUS_ACTIVE,
                'inactive',
                User::ACCOUNT_STATUS_SUSPENDED,
                User::ACCOUNT_STATUS_ARCHIVED,
            ])],
            'for_device' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $householdQuery = $this->userQuery($validated, true)
            ->when($request->boolean('for_device'), function ($query): void {
                $query->where('is_active', true)
                    ->where(function ($builder): void {
                        $builder->whereNull('account_status')
                            ->orWhere('account_status', User::ACCOUNT_STATUS_ACTIVE);
                    });
            });

        // The device and audience selectors need the complete eligible set;
        // customer management itself continues to use pagination below.
        $users = $request->boolean('for_device')
            ? $householdQuery->orderBy('name')->get()
            : $householdQuery->orderBy('name')->paginate($validated['per_page'] ?? 10)->withQueryString();

        return response()->json([
            'data' => $users,
        ]);
    }

    private function userQuery(array $validated, bool $householdsOnly = false)
    {
        return User::query()
            ->when($householdsOnly, fn ($query) => $query->where('role', 'user'))
            ->with([
                'assignedHousehold:id,name,email,account_number',
                ...($householdsOnly ? [
                    'devices:id,household_id,name,asset_number,serial_number,status,last_seen_at,last_telemetry_at',
                ] : []),
            ])
            ->when(! empty($validated['search']), function ($query) use ($validated): void {
                $search = '%'.$validated['search'].'%';
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('account_number', 'like', $search)
                        ->orWhere('address', 'like', $search)
                        ->orWhereHas('devices', function ($deviceQuery) use ($search): void {
                            $deviceQuery->where('name', 'like', $search)
                                ->orWhere('asset_number', 'like', $search)
                                ->orWhere('serial_number', 'like', $search)
                                ->orWhere('hardware_id', 'like', $search);
                        });
                });
            })
            ->when(array_key_exists('is_active', $validated), fn ($query) => $query->where('is_active', $validated['is_active']))
            ->when(! empty($validated['account_status']), function ($query) use ($validated): void {
                $status = $validated['account_status'];

                if ($status === 'inactive') {
                    $query->where('is_active', false);
                    return;
                }

                if ($status === User::ACCOUNT_STATUS_ACTIVE) {
                    $query->where('is_active', true)
                        ->where(function ($builder): void {
                            $builder->whereNull('account_status')
                                ->orWhere('account_status', User::ACCOUNT_STATUS_ACTIVE);
                        });
                    return;
                }

                $query->where('account_status', $status);
            });
    }

    public function store(Request $request)
    {
        $input = $request->all();
        if (array_key_exists('address', $input) && is_string($input['address'])) {
            $input['address'] = trim($input['address']);
        }

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['user', 'admin', 'device'])],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'address' => [
                Rule::requiredIf(fn (): bool => ($input['role'] ?? null) === 'user'),
                'nullable', 'string', 'max:255', 'not_regex:/^\s*$/',
            ],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        return $this->createAdminAccount($request, $validator->validated());
    }

    public function storeHousehold(Request $request)
    {
        $input = $request->all();
        $input['address'] = is_string($input['address'] ?? null) ? trim($input['address']) : $input['address'] ?? null;

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'address' => ['required', 'string', 'max:255', 'not_regex:/^\s*$/'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        return $this->createAdminAccount($request, $validator->validated(), 'user');
    }

    private function createAdminAccount(Request $request, array $validated, ?string $forcedRole = null)
    {
        $admin = $request->user();
        $role = $forcedRole ?? $validated['role'];

        $user = DB::transaction(function () use ($validated, $admin, $role): User {
            $user = User::create([
                ...$validated,
                'role' => $role,
                'assigned_user_id' => $role === 'device' ? ($validated['assigned_user_id'] ?? null) : null,
                'account_number' => User::generateAccountNumber(),
                'created_by_admin' => true,
                'created_by' => $admin->id,
                'approved_at' => now(),
                'account_status' => User::ACCOUNT_STATUS_ACTIVE,
            ]);

            $this->billingStatementService->recordAudit(
                $admin,
                'user_created',
                'Created a user account.',
                ['user_id' => $user->id],
                $user,
            );

            return $user->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => $user->role === 'user'
                ? 'Household user account created successfully. The user can now log in.'
                : 'User account created successfully.',
            'data' => [
                'user' => $user,
            ],
        ], 201);
    }

    private function validationError(\Illuminate\Contracts\Validation\Validator $validator)
    {
        return response()->json([
            'success' => false,
            'message' => 'Please correct the highlighted fields.',
            'errors' => $validator->errors(),
        ], 422);
    }

    public function show(User $user)
    {
        return response()->json([
            'data' => $user,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $input = $request->all();
        if (array_key_exists('address', $input) && is_string($input['address'])) {
            $input['address'] = trim($input['address']);
        }

        $validator = Validator::make($input, [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role' => ['sometimes', Rule::in(['user', 'admin', 'device'])],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'account_number')->ignore($user->id)],
            'address' => ['sometimes', 'required', 'string', 'max:255', 'not_regex:/^\s*$/'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'account_status' => ['sometimes', Rule::in([
                User::ACCOUNT_STATUS_ACTIVE,
                User::ACCOUNT_STATUS_SUSPENDED,
                User::ACCOUNT_STATUS_ARCHIVED,
            ])],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $validated = $validator->validated();

        if (array_key_exists('password', $validated) && $validated['password'] === null) {
            unset($validated['password']);
        }

        $nextRole = $validated['role'] ?? $user->role;
        if ($nextRole !== 'device') {
            $validated['assigned_user_id'] = null;
        }

        $user->update($validated);

        $user->refresh();
        if (! $user->isAccountActive()) {
            $user->tokens()->delete();
        }

        $this->billingStatementService->recordAudit(
            $request->user(),
            'user_updated',
            'Updated a user account.',
            ['user_id' => $user->id],
            $user,
        );

        if (array_key_exists('is_active', $validated) && $validated['is_active'] === false) {
            $this->billingStatementService->recordAudit(
                $request->user(),
                'user_deactivated',
                'Deactivated a household or user account.',
                ['user_id' => $user->id],
                $user,
            );
        } elseif (($validated['account_status'] ?? null) === User::ACCOUNT_STATUS_SUSPENDED) {
            $this->billingStatementService->recordAudit(
                $request->user(),
                'user_suspended',
                'Suspended a household or user account.',
                ['user_id' => $user->id],
                $user,
            );
        }

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => $user->fresh(),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        $user->tokens()->delete();
        $user->delete();

        $this->billingStatementService->recordAudit(
            $request->user(),
            'user_deleted',
            'Deleted a user account.',
            ['user_id' => $user->id],
            $user,
        );

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }
}
