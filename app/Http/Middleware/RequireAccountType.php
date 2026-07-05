<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireAccountType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        if (!$request->user() || $request->user()->account_type !== $type) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return $next($request);
    }
}
