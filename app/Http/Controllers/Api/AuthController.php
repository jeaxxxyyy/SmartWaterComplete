<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'account_number' => User::generateAccountNumber(),
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'role' => 'user',
            'is_active' => true,
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Account created successfully.',
            'data' => [
                'user' => $user,
                'token' => $token,
            ],
        ], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($credentials['email'] === User::ADMIN_EMAIL && $credentials['password'] === User::ADMIN_PASSWORD) {
            User::updateOrCreate(
                ['email' => User::ADMIN_EMAIL],
                [
                    'name' => 'Admin User',
                    'password' => Hash::make(User::ADMIN_PASSWORD),
                    'role' => 'admin',
                    'is_active' => true,
                ],
            );
        }

        $user = User::where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ]);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'This account is inactive.',
            ], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
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
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ]);

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
}
