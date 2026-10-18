<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePremium
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && $user->hasAccess(), 402, 'Your free trial has ended. Subscribe to keep reading your affirmations.');
        return $next($request);
    }
}
