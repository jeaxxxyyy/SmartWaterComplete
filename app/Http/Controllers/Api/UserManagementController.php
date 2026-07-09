<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BillingStatementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function __construct(
        protected BillingStatementService $billingStatementService
    ) {
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', Rule::in(['user', 'admin', 'device'])],
            'is_active' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()
            ->when(! empty($validated['search']), function ($query) use ($validated): void {
                $search = '%' . $validated['search'] . '%';
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('account_number', 'like', $search);
                });
            })
            ->when(! empty($validated['role']), fn ($query) => $query->where('role', $validated['role']))
            ->when(array_key_exists('is_active', $validated), fn ($query) => $query->where('is_active', $validated['is_active']))
            ->latest()
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();

        return response()->json([
            'data' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(['user', 'admin', 'device'])],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user = User::create([
            ...$validated,
            'account_number' => User::generateAccountNumber(),
        ]);

        $this->billingStatementService->recordAudit(
            $request->user(),
            'user_created',
            'Created a user account.',
            ['user_id' => $user->id],
            $user,
        );

        return response()->json([
            'message' => 'User created successfully.',
            'data' => $user,
        ], 201);
    }

    public function show(User $user)
    {
        return response()->json([
            'data' => $user,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role' => ['sometimes', Rule::in(['user', 'admin', 'device'])],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('users', 'account_number')->ignore($user->id)],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('password', $validated) && $validated['password'] === null) {
            unset($validated['password']);
        }

        $user->update($validated);

        $this->billingStatementService->recordAudit(
            $request->user(),
            'user_updated',
            'Updated a user account.',
            ['user_id' => $user->id],
            $user,
        );

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => $user->fresh(),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
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
