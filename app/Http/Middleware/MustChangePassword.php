<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MustChangePassword
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password || $request->routeIs([
            'password.change.form',
            'password.change.update',
        ])) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Password change is required.',
                'code' => 'PASSWORD_CHANGE_REQUIRED',
            ], 403);
        }

        return redirect()->route('password.change.form');
    }
}
