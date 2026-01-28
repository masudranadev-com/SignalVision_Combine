<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->guard('admin')->check()) {
            return redirect()->route('login');
        }

        if (auth()->guard('admin')->user()->status !== 'active') {
            auth()->guard('admin')->logout();
            return redirect()->route('login')->withErrors(['username' => 'Your account is inactive.']);
        }

        return $next($request);
    }
}
