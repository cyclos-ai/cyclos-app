<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\Central\Plan;
use App\Services\Stripe\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(private readonly StripeService $stripe)
    {
    }

    /**
     * GET /api/v1/billing/plans
     */
    public function plans(): JsonResponse
    {
        $plans = Plan::where('is_active', true)->get()->map(function (Plan $plan) {
            return [
                'id'                 => $plan->id,
                'name'               => $plan->name,
                'price_monthly'      => $plan->price_monthly,
                'price_yearly'       => $plan->price_yearly,
                'included_ai_tokens' => $plan->included_ai_tokens,
                'included_api_calls' => $plan->included_api_calls,
                'features'           => $plan->features,
            ];
        });

        return $this->success($plans);
    }

    /**
     * GET /api/v1/billing/current
     */
    public function current(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            return $this->notFound('No tenant associated with this account.');
        }

        $tenant->loadMissing(['plan', 'subscription']);
        $plan = $tenant->plan;

        $usage = $this->resolveUsage($tenant->id);

        return $this->success([
            'plan'         => $plan ? [
                'id'                 => $plan->id,
                'name'               => $plan->name,
                'price_monthly'      => $plan->price_monthly,
                'price_yearly'       => $plan->price_yearly,
                'included_ai_tokens' => $plan->included_ai_tokens,
                'included_api_calls' => $plan->included_api_calls,
                'features'           => $plan->features,
            ] : null,
            'subscription' => $tenant->subscription,
            'usage'        => [
                'ai_tokens' => [
                    'used'     => $usage['ai_tokens'],
                    'included' => $plan?->included_ai_tokens ?? 0,
                ],
                'api_calls' => [
                    'used_external' => $usage['api_calls_external'],
                    'used_total'    => $usage['api_calls_total'],
                    'included'      => $plan?->included_api_calls ?? 0,
                ],
            ],
            'trial_ends_at' => $tenant->trial_ends_at,
        ]);
    }

    /**
     * POST /api/v1/billing/checkout
     */
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_id'       => ['required', 'integer'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
        ]);

        if (! $this->stripe->isConfigured()) {
            return $this->error('Billing is not configured.', 422);
        }

        $tenant = $request->user()->tenant;

        if (! $tenant) {
            return $this->notFound('No tenant associated with this account.');
        }

        $plan = Plan::where('is_active', true)->find($validated['plan_id']);

        if (! $plan) {
            return $this->error('Plan not found.', 422);
        }

        try {
            $url = $this->stripe->createCheckoutSession($tenant, $plan, $validated['billing_cycle']);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(['checkout_url' => $url]);
    }

    /**
     * POST /api/v1/billing/portal
     */
    public function portal(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if (! $tenant) {
            return $this->notFound('No tenant associated with this account.');
        }

        if (empty($tenant->stripe_customer_id)) {
            return $this->error('No Stripe customer on file for this account.', 422);
        }

        try {
            $url = $this->stripe->createBillingPortalSession($tenant);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(['portal_url' => $url]);
    }

    /**
     * Resolve usage from the sibling-built metering service, defaulting to
     * zeros if it is not yet available so this endpoint never 500s.
     *
     * @return array{ai_tokens: int, api_calls_external: int, api_calls_total: int}
     */
    private function resolveUsage($tenantId): array
    {
        $default = ['ai_tokens' => 0, 'api_calls_external' => 0, 'api_calls_total' => 0];

        $serviceClass = \App\Services\Billing\UsageMeteringService::class;

        if (! class_exists($serviceClass)) {
            return $default;
        }

        try {
            $summary = app($serviceClass)->usageSummary($tenantId);

            return [
                'ai_tokens'          => (int) ($summary['ai_tokens'] ?? 0),
                'api_calls_external' => (int) ($summary['api_calls_external'] ?? 0),
                'api_calls_total'    => (int) ($summary['api_calls_total'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
