<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->is_active) {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'Akun Anda sedang dinonaktifkan.']);
        }

        return $next($request);
    }
}
