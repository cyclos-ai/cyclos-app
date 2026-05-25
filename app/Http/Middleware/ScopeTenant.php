<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScopeTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!tenancy()->initialized) {
            return response()->json([
                'error' => 'Tenant not initialized',
                'message' => 'A valid organization context is required for this endpoint.',
            ], 403);
        }

        return $next($request);
    }
}
