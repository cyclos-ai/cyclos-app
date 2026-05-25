<?php

namespace App\Http\Middleware;

use App\Models\Central\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InitializeTenancy
{
    public function handle(Request $request, Closure $next): Response
    {
        $organizationUuid = $request->header('X-Organization-UUID')
            ?? $request->route('organization_uuid')
            ?? $request->input('organization_uuid');

        if (!$organizationUuid || $organizationUuid === '*') {
            return $next($request);
        }

        $tenant = Tenant::find($organizationUuid);

        if (!$tenant) {
            return response()->json([
                'error' => 'Organization not found',
                'message' => 'The specified organization_uuid does not exist.',
            ], 404);
        }

        tenancy()->initialize($tenant);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        tenancy()->end();
    }
}
