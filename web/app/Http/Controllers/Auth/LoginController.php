<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Hanya login dan logout. Tidak ada pendaftaran publik dan reset kata sandi:
 * akun dibuat lewat seeder.
 */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Cegah session fixation.
        $request->session()->regenerate();

        AuditLog::record('login', $request->user()->getAuthIdentifier(), null, $request->ip());

        return redirect()->intended(route('cases.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $userId = $request->user()?->getAuthIdentifier();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        AuditLog::record('logout', $userId, null, $request->ip());

        return redirect()->route('login');
    }
}
