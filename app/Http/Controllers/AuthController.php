<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show the application login form.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->to(Auth::user()->getRedirectUrl());
        }

        return view('auth.login');
    }

    /**
     * Handle an authentication attempt.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Username wajib diisi.',
            'password.required' => 'Password wajib diisi.',
        ]);

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            if ($user->status !== 'active') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()->withErrors([
                    'username' => 'Akun Anda dinonaktifkan oleh administrator. Silakan hubungi tim IT.',
                ])->onlyInput('username');
            }

            $request->session()->regenerate();

            $defaultUrl = $user->getRedirectUrl();
            $intendedUrl = session()->pull('url.intended');

            $redirectUrl = $defaultUrl;
            if ($intendedUrl) {
                $intendedPath = parse_url($intendedUrl, PHP_URL_PATH) ?: '';
                if ($intendedUrl !== route('login') && $intendedUrl !== url('/login') && $intendedUrl !== url('/') && $intendedPath !== '/') {
                    if ($intendedUrl === route('dashboard') || $intendedUrl === url('/dashboard') || $intendedPath === '/dashboard') {
                        if ($user->hasPermission('dashboard')) {
                            $redirectUrl = $intendedUrl;
                        }
                    } else {
                        $redirectUrl = $intendedUrl;
                    }
                }
            }

            return redirect()->to($redirectUrl)
                ->with('success', 'Selamat datang kembali, '.$user->name.'!');
        }

        return back()->withErrors([
            'username' => 'Username atau password yang Anda masukkan salah.',
        ])->onlyInput('username');
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Anda telah berhasil keluar dari sistem.');
    }
}
