<?php

namespace App\Http\Middleware;

use App\Services\Auth\UserSessionTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TouchSessionPresence
{
    public function __construct(
        private UserSessionTracker $sessions,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $this->sessions->touch($request, $request->user());
        }

        return $next($request);
    }
}
