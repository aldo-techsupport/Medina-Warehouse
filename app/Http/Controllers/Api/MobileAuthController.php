<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MobileAuthController extends Controller
{
    /**
     * Authenticate mobile app user and return API token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'Username atau email wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $loginInput = trim($validated['username']);

        $user = User::where('username', $loginInput)
            ->orWhere('email', $loginInput)
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'code' => 401,
                'message' => 'Username atau password yang Anda masukkan salah.',
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'code' => 403,
                'message' => 'Akun Anda dinonaktifkan oleh administrator gudang.',
            ], 403);
        }

        $plainToken = $user->generateApiToken();

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'Login berhasil. Selamat datang kembali, '.$user->name.'!',
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role?->slug,
                'role_name' => $user->role?->name,
                'permissions' => $user->role?->permissions ?? [],
            ],
        ]);
    }

    /**
     * Log out mobile user by revoking current API token.
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof User) {
            $user->revokeApiToken();
        }

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'Anda telah berhasil logout dari aplikasi mobile.',
        ]);
    }

    /**
     * Get profile of currently authenticated mobile user.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'code' => 401,
                'message' => 'Tidak terautentikasi.',
            ], 401);
        }

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role?->slug,
                'role_name' => $user->role?->name,
                'permissions' => $user->role?->permissions ?? [],
                'last_active_at' => $user->api_token_last_used_at?->translatedFormat('d M Y, H:i:s'),
            ],
        ]);
    }

    /**
     * Get list of active warehouse operators / packers for quick mobile selection.
     */
    public function packers(): JsonResponse
    {
        $packers = User::with('role')
            ->where('status', 'active')
            ->get()
            ->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                    'role_name' => $u->role?->name ?? 'Operator',
                    'role_slug' => $u->role?->slug ?? 'operator_packing',
                ];
            });

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'data' => $packers,
        ]);
    }
}

