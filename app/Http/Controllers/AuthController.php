<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Handle user authentication and generate API token
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'kode_user' => 'required|string',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'kode_user' => ['Invalid user code or password.'],
            ]);
        }

        $user = Auth::user();
        $token = $user->createToken($request->userAgent() ?? 'auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user,
        ]);
    }
    
    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'kode_user' => 'required|string|exists:users,kode_user',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = \App\Models\User::where('kode_user', $validated['kode_user'])->firstOrFail();
        $user->update(['password' => bcrypt($validated['password'])]);
        $user->tokens()->delete();

        return response()->json(['message' => 'Password berhasil direset.']);
    }

    /**
     * Logout current device by revoking current token
     */
    public function logout(Request $request)
    {
        /** @var PersonalAccessToken|null $token */
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    /**
     * Logout from all devices by revoking all tokens
     */
    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out from all devices',
        ]);
    }

    /**
     * Get current authenticated user data
     */
    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }
}
