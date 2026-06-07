<?php

namespace App\Services\Stripe;

use App\Models\Central\Plan;
use App\Models\Central\Subscription;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Log;

class StripeService
{
    public const METER_TOKENS = 'cyclos_ai_tokens';
    public const METER_CALLS  = 'cyclos_api_calls';

    private ?string $secret;
    private \Stripe\StripeClient $client;

    public function __construct()
    {
        $this->secret = config('services.stripe.secret');
        $this->client = new \Stripe\StripeClient((string) $this->secret);
    }

    /**
     * Check if the Stripe secret key is configured.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->secret);
    }

    /**
     * Return the tenant's existing Stripe customer id, creating one if needed.
     */
    public function customerFor(Tenant $tenant): string
    {
        if (! empty($tenant->stripe_customer_id)) {
            return $tenant->stripe_customer_id;
        }

        try {
            $customer = $this->client->customers->create([
                'name'  => $tenant->name,
                'email' => $tenant->billing_email ?: $tenant->email,
                'metadata' => [
                    'tenant_id' => (string) $tenant->id,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe: failed to create customer', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);

            throw new \RuntimeException('Unable to create Stripe customer.');
        }

        $tenant->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    /**
     * Create a subscription-mode Checkout Session and return its hosted URL.
     *
     * @param  string  $cycle  'monthly' or 'yearly'
     */
    public function createCheckoutSession(Tenant $tenant, Plan $plan, string $cycle): string
    {
        $basePrice = $cycle === 'yearly'
            ? $plan->stripe_price_yearly_id
            : $plan->stripe_price_monthly_id;

        if (empty($basePrice)) {
            throw new \RuntimeException('Plan is missing a Stripe price for the selected billing cycle.');
        }

        $lineItems = [
            ['price' => $basePrice, 'quantity' => 1],
        ];

        // Metered overage prices carry no quantity.
        if (! empty($plan->stripe_meter_price_tokens_id)) {
            $lineItems[] = ['price' => $plan->stripe_meter_price_tokens_id];
        }

        if (! empty($plan->stripe_meter_price_calls_id)) {
            $lineItems[] = ['price' => $plan->stripe_meter_price_calls_id];
        }

        $base = config('app.frontend_url') ?: config('app.url');

        try {
            $session = $this->client->checkout->sessions->create([
                'mode'        => 'subscription',
                'customer'    => $this->customerFor($tenant),
                'line_items'  => $lineItems,
                'success_url' => $base . '/settings/billing?billing=success',
                'cancel_url'  => $base . '/settings/billing?billing=cancelled',
                'metadata'    => [
                    'tenant_id' => (string) $tenant->id,
                    'plan_id'   => (string) $plan->id,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe: failed to create checkout session', [
                'tenant_id' => $tenant->id,
                'plan_id'   => $plan->id,
                'error'     => $e->getMessage(),
            ]);

            throw new \RuntimeException('Unable to start the checkout session.');
        }

        return $session->url;
    }

    /**
     * Create a Billing Portal session for the tenant and return its URL.
     */
    public function createBillingPortalSession(Tenant $tenant): string
    {
        $base = config('app.frontend_url') ?: config('app.url');

        try {
            $session = \Stripe\BillingPortal\Session::create([
                'customer'   => $this->customerFor($tenant),
                'return_url' => $base . '/settings/billing',
            ], ['api_key' => $this->secret]);
        } catch (\Throwable $e) {
            Log::error('Stripe: failed to create billing portal session', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
            ]);

            throw new \RuntimeException('Unable to open the billing portal.');
        }

        return $session->url;
    }

    /**
     * Upsert the local subscription from a Stripe Subscription object and
     * align the tenant's plan with the plan matching the subscription price.
     */
    public function syncSubscriptionFromStripe(\Stripe\Subscription $sub): void
    {
        // Stripe API 2025+/stripe-php v17+ moved the billing period onto the
        // subscription item; fall back to the subscription root for older shapes.
        $item = $sub->items->data[0] ?? null;

        $priceId = $item?->price?->id;
        $interval = $item?->price?->recurring?->interval;
        $billingCycle = $interval === 'year' ? 'yearly' : 'monthly';

        $periodStart = $item?->current_period_start ?? $sub->current_period_start ?? null;
        $periodEnd = $item?->current_period_end ?? $sub->current_period_end ?? null;

        $plan = $this->planForPrice($priceId);

        $tenant = Tenant::where('stripe_customer_id', $sub->customer)->first();

        $subscription = Subscription::where('stripe_subscription_id', $sub->id)->first();

        $attributes = [
            'status'               => $sub->status,
            'billing_cycle'        => $billingCycle,
            'stripe_customer_id'   => $sub->customer,
            'stripe_price_id'      => $priceId,
            'current_period_start' => $periodStart
                ? \Illuminate\Support\Carbon::createFromTimestamp($periodStart)
                : now(),
            'current_period_end'   => $periodEnd
                ? \Illuminate\Support\Carbon::createFromTimestamp($periodEnd)
                : now(),
            'canceled_at'          => $sub->canceled_at
                ? \Illuminate\Support\Carbon::createFromTimestamp($sub->canceled_at)
                : null,
        ];

        if ($subscription) {
            $subscription->forceFill($attributes)->save();
        } elseif ($tenant) {
            $subscription = new Subscription();
            $subscription->forceFill(array_merge($attributes, [
                'tenant_id'              => $tenant->id,
                'plan_id'                => $plan?->id ?? $tenant->plan_id,
                'stripe_subscription_id' => $sub->id,
            ]))->save();
        }

        if ($tenant && $plan) {
            $tenant->forceFill(['plan_id' => $plan->id])->save();
        }
    }

    /**
     * Report a usage event to a Stripe Billing Meter. Never throws.
     */
    public function reportMeterEvent(string $stripeCustomerId, string $eventName, int $value): void
    {
        try {
            \Stripe\Billing\MeterEvent::create([
                'event_name' => $eventName,
                'payload'    => [
                    'stripe_customer_id' => $stripeCustomerId,
                    'value'              => (string) $value,
                ],
            ], ['api_key' => $this->secret]);
        } catch (\Throwable $e) {
            Log::error('Stripe: failed to report meter event', [
                'stripe_customer_id' => $stripeCustomerId,
                'event_name'         => $eventName,
                'error'              => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve the local Plan whose Stripe price ids match the given price.
     */
    private function planForPrice(?string $priceId): ?Plan
    {
        if (empty($priceId)) {
            return null;
        }

        return Plan::where('stripe_price_monthly_id', $priceId)
            ->orWhere('stripe_price_yearly_id', $priceId)
            ->first();
    }
}
