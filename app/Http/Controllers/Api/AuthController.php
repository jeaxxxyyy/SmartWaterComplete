<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BillingStatementService;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const GENERIC_LOGIN_ERROR = 'Unable to log in. Please check your credentials or contact the administrator.';

    private const INACTIVE_ACCOUNT_ERROR = 'This account is currently inactive. Please contact the administrator.';

    public function __construct(protected BillingStatementService $audit) {}

    public function register(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Public registration is disabled. Please contact an administrator to create an account.',
        ], 403);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $credentials['email'] = strtolower(trim($credentials['email']));

        if ($credentials['email'] === User::ADMIN_EMAIL && $credentials['password'] === User::ADMIN_PASSWORD) {
            User::updateOrCreate(
                ['email' => User::ADMIN_EMAIL],
                [
                    'name' => 'Admin User',
                    'password' => Hash::make(User::ADMIN_PASSWORD),
                    'role' => 'admin',
                    'is_active' => true,
                    'created_by_admin' => true,
                ],
            );
        }

        $user = User::where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            $this->recordLoginFailure($credentials['email'], 'invalid_credentials');
            throw ValidationException::withMessages([
                'email' => [self::GENERIC_LOGIN_ERROR],
            ]);
        }

        if (! $user->isAccountActive()) {
            $this->audit->recordAudit($user, 'login_failed', 'Login was denied for an inactive account.', [], $user, 'failure');
            return response()->json([
                'success' => false,
                'message' => self::INACTIVE_ACCOUNT_ERROR,
            ], 403);
        }

        if ($user->isHousehold() && ! $user->canHouseholdLogin()) {
            $this->audit->recordAudit($user, 'login_failed', 'Login was denied because the household account is not approved.', [], $user, 'failure');
            throw ValidationException::withMessages([
                'email' => [self::GENERIC_LOGIN_ERROR],
            ]);
        }

        $token = $user->createToken('api-token')->plainTextToken;
        $this->audit->recordAudit($user, 'login_success', 'User authenticated successfully.', [], $user);

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }

    public function deviceToken(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if ($user === null || ! $user->isDevice()) {
            throw ValidationException::withMessages([
                'email' => ['Device credentials are invalid.'],
            ]);
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Device credentials are invalid.'],
            ]);
        }

        $token = $user->createToken($credentials['device_name'] ?? 'water-device', ['device-upload'])->plainTextToken;

        return response()->json([
            'message' => 'Device token issued successfully.',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ]);
    }

    public function listDeviceTokens(User $user)
    {
        if (! $user->isDevice()) {
            return response()->json([
                'message' => 'Device accounts only.',
            ], 422);
        }

        return response()->json([
            'data' => $user->tokens()->latest()->get(),
        ]);
    }

    public function issueDeviceToken(Request $request, User $user)
    {
        if (! $user->isDevice()) {
            return response()->json([
                'message' => 'Device accounts only.',
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'abilities' => ['sometimes', 'array'],
            'abilities.*' => ['string'],
        ]);

        $token = $user->createToken(
            $validated['name'] ?? 'water-device',
            $validated['abilities'] ?? ['device-upload']
        );

        return response()->json([
            'message' => 'Device token issued successfully.',
            'data' => [
                'token' => $token->plainTextToken,
                'access_token' => $token->accessToken,
            ],
        ], 201);
    }

    public function revokeDeviceToken(User $user, int $tokenId)
    {
        if (! $user->isDevice()) {
            return response()->json([
                'message' => 'Device accounts only.',
            ], 422);
        }

        $deleted = $user->tokens()->whereKey($tokenId)->delete();

        if ($deleted === 0) {
            return response()->json([
                'message' => 'Token not found.',
            ], 404);
        }

        return response()->json([
            'message' => 'Device token revoked successfully.',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'data' => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        $this->audit->recordAudit($request->user(), 'logout', 'User signed out.', [], $request->user());
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if ($user === null || ($user->isHousehold() && ! $user->canHouseholdLogin())) {
            return response()->json([
                'message' => 'If the account is eligible, password reset instructions will be sent.',
            ]);
        }

        $status = Password::sendResetLink($validated);

        return response()->json([
            'message' => __($status),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::query()->where('email', $validated['email'])->first();
        if ($user === null || ($user->isHousehold() && ! $user->canHouseholdLogin())) {
            return response()->json([
                'message' => 'Unable to reset the password for this account.',
            ], 422);
        }

        $status = Password::reset(
            $validated,
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => __($status),
            ], 422);
        }

        $this->audit->recordAudit($user, 'password_reset', 'Password was reset successfully.', [], $user);

        return response()->json([
            'message' => 'Password reset successfully.',
        ]);
    }

    public function sendVerificationEmail(Request $request)
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ]);
        }

        $request->user()?->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Verification link sent.',
        ]);
    }

    public function verifyEmail(EmailVerificationRequest $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email already verified.',
            ]);
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return response()->json([
            'message' => 'Email verified successfully.',
        ]);
    }

    private function recordLoginFailure(string $email, string $reason): void
    {
        $this->audit->recordAudit(null, 'login_failed', 'A login attempt was rejected.', [
            'email_hash' => hash('sha256', strtolower(trim($email))),
            'reason' => $reason,
        ], null, 'failure');
    }
}
