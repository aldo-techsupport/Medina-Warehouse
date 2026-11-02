<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobileApi
{
    /**
     * Handle an incoming mobile API request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $token = $this->extractToken($request);
        $stationKey = config('app.warehouse_api_key', env('WAREHOUSE_API_KEY'));

        if (! empty($token)) {
            // Check master station API key if configured
            if (! empty($stationKey) && hash_equals($stationKey, $token)) {
                $defaultUser = User::where('status', 'active')->first();
                if ($defaultUser) {
                    auth()->setUser($defaultUser);
                    $request->setUserResolver(fn () => $defaultUser);
                }

                return $next($request);
            }

            // Check standard User API Token
            $user = User::findByApiToken($token);

            if ($user) {
                auth()->setUser($user);
                $request->setUserResolver(fn () => $user);

                return $next($request);
            }

            return response()->json([
                'status' => 'error',
                'code' => 401,
                'message' => 'Token otentikasi mobile tidak valid atau sudah kedaluwarsa. Silakan login kembali.',
            ], 401);
        }

        // If mode is optional or station mode, allow guest
        if ($mode === 'optional') {
            return $next($request);
        }

        // Check if master station key passed via X-API-KEY header
        $headerKey = $request->header('X-API-KEY');
        if (! empty($stationKey) && ! empty($headerKey) && hash_equals($stationKey, $headerKey)) {
            $defaultUser = User::where('status', 'active')->first();
            if ($defaultUser) {
                auth()->setUser($defaultUser);
                $request->setUserResolver(fn () => $defaultUser);
            }

            return $next($request);
        }

        return response()->json([
            'status' => 'error',
            'code' => 401,
            'message' => 'Otentikasi diperlukan. Kirimkan Bearer token pada header Authorization atau login melalui POST /api/v1/auth/login.',
        ], 401);
    }

    /**
     * Extract token from request headers or query string.
     */
    protected function extractToken(Request $request): ?string
    {
        $authHeader = $request->header('Authorization');
        if (! empty($authHeader) && preg_match('/Bearer\s+(\S+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        if ($request->hasHeader('X-API-TOKEN')) {
            return (string) $request->header('X-API-TOKEN');
        }

        if ($request->filled('api_token')) {
            return (string) $request->input('api_token');
        }

        return null;
    }
}
