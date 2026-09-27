<?php

declare(strict_types=1);

namespace BlatUI\Admin\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Get admin guard name.
     */
    protected function guard(): string
    {
        return (string) config('blatui-admin.auth.guard', 'admin');
    }

    /**
     * Show login page.
     */
    public function getLogin(): Response|RedirectResponse
    {
        if (Auth::guard($this->guard())->check()) {
            return redirect()->intended($this->redirectPath());
        }

        return response()->view('blatui-admin::auth.login');
    }

    /**
     * Handle a login request.
     */
    public function postLogin(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $remember = (bool) $request->input('remember', false);

        if (Auth::guard($this->guard())->attempt($credentials, $remember)) {
            $request->session()->regenerate();

            return redirect()->intended($this->redirectPath());
        }

        throw ValidationException::withMessages([
            'username' => [trans('auth.failed')],
        ]);
    }

    /**
     * Log the user out of the administration.
     */
    public function getLogout(Request $request): RedirectResponse
    {
        Auth::guard($this->guard())->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $prefix = trim((string) config('blatui-admin.route.prefix', 'admin'), '/');
        $loginUrl = $prefix ? '/'.$prefix.'/auth/login' : '/auth/login';

        return redirect($loginUrl);
    }

    /**
     * Get redirect path.
     */
    protected function redirectPath(): string
    {
        $prefix = trim((string) config('blatui-admin.route.prefix', 'admin'), '/');

        return $prefix ? '/'.$prefix : '/';
    }
}
