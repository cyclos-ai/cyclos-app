<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\Central\Subscription;
use App\Services\Stripe\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    public function __construct(private readonly StripeService $stripe)
    {
    }

    /**
     * POST /api/stripe/webhook
     *
     * Verifies the Stripe signature, then dispatches handled event types.
     * Returns 200 for handled/ignored events; 400 only on a bad signature.
     */
    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.stripe.webhook_secret');

        try {
            $event = \Stripe\Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature', ''),
                (string) $secret
            );
        } catch (\UnexpectedValueException | \Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook: signature verification failed', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'Invalid signature'], 400);
        }

        try {
            switch ($event->type) {
                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                case 'customer.subscription.deleted':
                    $this->stripe->syncSubscriptionFromStripe($event->data->object);
                    break;

                case 'invoice.paid':
                    $this->updateSubscriptionStatusFromInvoice($event->data->object, 'active');
                    break;

                case 'invoice.payment_failed':
                    $this->updateSubscriptionStatusFromInvoice($event->data->object, 'past_due');
                    break;

                default:
                    // Unhandled event types are acknowledged so Stripe stops retrying.
                    break;
            }
        } catch (\Throwable $e) {
            Log::error('Stripe webhook: handler error', [
                'event_type' => $event->type ?? 'unknown',
                'error'      => $e->getMessage(),
            ]);
        }

        return response()->json(['message' => 'ok'], 200);
    }

    /**
     * Update the local subscription status from an invoice event.
     */
    private function updateSubscriptionStatusFromInvoice(object $invoice, string $status): void
    {
        // Stripe API 2025+/stripe-php v17+ nests the subscription under
        // parent.subscription_details; fall back to the legacy root field.
        $subscriptionId = $invoice->parent?->subscription_details?->subscription
            ?? ($invoice->subscription ?? null);

        if (empty($subscriptionId)) {
            return;
        }

        // The nested value may be an expanded Subscription object.
        if (is_object($subscriptionId)) {
            $subscriptionId = $subscriptionId->id ?? null;
        }

        if (empty($subscriptionId)) {
            return;
        }

        $subscription = Subscription::where('stripe_subscription_id', $subscriptionId)->first();

        if ($subscription) {
            $subscription->forceFill(['status' => $status])->save();
        }
    }
}
