<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminAuthController extends Controller
{
    /**
     * Display the admin login form.
     */
    public function showLoginForm(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    /**
     * Handle an incoming admin authentication request.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $email = Str::lower($request->string('email')->value());
        $throttleKey = 'admin_login|'.Str::transliterate($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'status' => 'error',
                'message' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ], 429);
        }

        $admin = Admin::where('email', $email)->first();

        if (! $admin || ! Hash::check($request->string('password')->value(), $admin->password)) {
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid email or password.',
            ], 422);
        }

        if (! $admin->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is deactivated. Contact super admin.',
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        Auth::guard('admin')->login($admin, $request->boolean('remember'));

        $request->session()->regenerate();

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful! Redirecting...',
            'redirect_url' => route('admin.dashboard'),
        ]);
    }

    /**
     * Destroy an authenticated admin session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    /**
     * Display the forgot password link request form.
     */
    public function showForgotPasswordForm(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.forgot-password');
    }

    /**
     * Send a reset link to the given admin email.
     */
    public function sendResetLinkEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $email = Str::lower($request->string('email')->value());
        $throttleKey = 'admin_forgot|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'status' => 'error',
                'message' => "Too many password reset requests. Please try again in {$seconds} seconds.",
            ], 429);
        }

        RateLimiter::hit($throttleKey, 60);

        $admin = Admin::where('email', $email)->first();

        if (! $admin) {
            return response()->json([
                'status' => 'success',
                'message' => 'If an account exists with that email, a password reset link has been sent.',
            ]);
        }

        if (! $admin->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is deactivated. Contact super admin.',
            ], 403);
        }

        $status = Password::broker('admins')->sendResetLink(
            ['email' => $email]
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'status' => 'success',
                'message' => 'A password reset link has been sent to your email address.',
            ]);
        }

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'status' => 'error',
                'message' => __($status),
            ], 429);
        }

        return response()->json([
            'status' => 'error',
            'message' => __($status),
        ], 422);
    }

    /**
     * Display the password reset form.
     */
    public function showResetPasswordForm(Request $request, string $token): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    /**
     * Reset the given admin's password.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $email = Str::lower($request->string('email')->value());
        $admin = Admin::where('email', $email)->first();

        if ($admin && ! $admin->is_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Your account is deactivated. Contact super admin.',
            ], 403);
        }

        $status = Password::broker('admins')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Admin $adminUser, string $password): void {
                $adminUser->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'status' => 'success',
                'message' => 'Password reset successfully! You can now log in.',
                'redirect_url' => route('admin.login'),
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => __($status),
        ], 422);
    }
}
