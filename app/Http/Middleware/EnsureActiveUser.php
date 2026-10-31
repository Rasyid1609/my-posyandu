<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Inertia::clearHistory();

            return redirect()->route('login')->withErrors(['email' => 'Akun tidak aktif. Hubungi Ketua.']);
        }
        if (app()->isProduction() && ! config('posyandu.real_data_approved')) {
            abort(503, 'Penggunaan data nyata belum disetujui pengelola.');
        }

        return $next($request);
    }
}
