<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsurePremium
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        abort_unless($user && ($user->isAdmin() || $user->isPremium()), 402, 'A Higher Life Premium subscription is required.');
        return $next($request);
    }
}
