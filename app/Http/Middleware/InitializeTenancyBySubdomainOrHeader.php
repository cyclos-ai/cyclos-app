<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;

/**
 * Try subdomain-based tenant identification first.
 * If that fails (e.g. behind a tunnel with no subdomain),
 * fall back to the X-Tenant request header.
 */
class InitializeTenancyBySubdomainOrHeader
{
    public function handle(Request $request, Closure $next): mixed
    {
        // 1) Try the standard subdomain approach
        try {
            $subdomainMiddleware = app(InitializeTenancyBySubdomain::class);

            return $subdomainMiddleware->handle($request, $next);
        } catch (\Exception $e) {
            // Subdomain identification failed — try header fallback
        }

        // 2) Fall back to X-Tenant header
        $tenantId = $request->header('X-Tenant');

        if (! $tenantId) {
            abort(400, 'Tenant could not be identified. Provide a subdomain or X-Tenant header.');
        }

        $tenantModel = config('tenancy.tenant_model');
        $tenant = $tenantModel::find($tenantId);

        if (! $tenant) {
            abort(404, "Tenant [{$tenantId}] not found.");
        }

        tenancy()->initialize($tenant);

        return $next($request);
    }
}
