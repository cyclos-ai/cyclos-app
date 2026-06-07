<?php

namespace App\Http\Middleware;

use App\Services\Billing\UsageMeteringService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Counts every inbound tenant API request for analytics (NOT billed).
 * Runs after the response is produced; only records successful/handled
 * requests (status < 500) when tenancy is initialized. Never throws.
 */
class CountApiCallMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $tenantId = tenancy()->tenant?->id;

            if ($tenantId !== null && $response->getStatusCode() < 500) {
                app(UsageMeteringService::class)->recordApiCall($tenantId, 'inbound', false);
            }
        } catch (\Throwable $e) {
            // Metering must never break a request.
        }

        return $response;
    }
}
