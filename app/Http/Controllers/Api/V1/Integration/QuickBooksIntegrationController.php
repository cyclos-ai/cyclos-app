<?php

namespace App\Http\Controllers\Api\V1\Integration;

use App\Http\Controllers\Controller;
use App\Models\Tenant\QuickBooksCredential;
use App\Services\QuickBooks\QuickBooksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QuickBooksIntegrationController extends Controller
{
    public function __construct(
        private readonly QuickBooksService $quickbooks,
    ) {}

    /**
     * GET /api/v1/integrations/quickbooks
     * Current QuickBooks connection status.
     */
    public function status(): JsonResponse
    {
        $credential = QuickBooksCredential::current();

        return $this->success([
            'is_configured'    => $this->quickbooks->isConfigured(),
            'is_connected'     => $this->quickbooks->isConnected(),
            'company_name'     => $credential?->company_name,
            'realm_id'         => $this->maskRealmId($credential?->realm_id),
            'environment'      => $credential?->environment ?? config('services.quickbooks.environment', 'production'),
            'last_sync_at'     => $credential?->last_sync_at?->toIso8601String(),
            'token_expires_at' => $credential?->token_expires_at?->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/integrations/quickbooks/connect
     * Generate the Intuit authorization URL.
     */
    public function connect(): JsonResponse
    {
        if (! $this->quickbooks->isConfigured()) {
            return $this->error('QuickBooks client credentials not set', 422);
        }

        $state = Crypt::encryptString(json_encode([
            't'  => tenancy()->tenant?->id,
            'n'  => Str::random(24),
            'ts' => now()->timestamp,
        ]));

        return $this->success([
            'authorization_url' => $this->quickbooks->getAuthorizationUrl($state),
        ]);
    }

    /**
     * GET /quickbooks/callback (WEB route — no Bearer token).
     * OAuth2 redirect target from Intuit. Validates state, exchanges the
     * code for tokens, then redirects the browser back into the SPA.
     */
    public function callback(Request $request): RedirectResponse
    {
        $code    = $request->query('code');
        $realmId = $request->query('realmId');
        $state   = $request->query('state');

        if (! $code || ! $realmId || ! $state) {
            return redirect('/settings/quickbooks?qb_error=' . urlencode('Missing authorization parameters.'));
        }

        if (! $this->isStateValid($state)) {
            return redirect('/settings/quickbooks?qb_error=' . urlencode('Invalid or expired authorization state.'));
        }

        try {
            $this->quickbooks->handleCallback($code, $realmId);
        } catch (\Throwable $e) {
            Log::error('QuickBooks: callback handling failed', ['message' => $e->getMessage()]);

            return redirect('/settings/quickbooks?qb_error=' . urlencode($e->getMessage()));
        }

        return redirect('/settings/quickbooks?qb=connected');
    }

    /**
     * POST /api/v1/integrations/quickbooks/disconnect
     * Disconnect QuickBooks.
     */
    public function disconnect(): JsonResponse
    {
        $this->quickbooks->disconnect();

        return $this->success(null, 'QuickBooks disconnected');
    }

    /**
     * Validate the OAuth state: decrypt and check the timestamp age (< 600s).
     */
    private function isStateValid(string $state): bool
    {
        try {
            $payload = json_decode(Crypt::decryptString($state), true);
        } catch (\Throwable $e) {
            return false;
        }

        if (! is_array($payload) || empty($payload['ts'])) {
            return false;
        }

        return (now()->timestamp - (int) $payload['ts']) < 600;
    }

    /**
     * Mask a realm ID to its last 4 digits.
     */
    private function maskRealmId(?string $realmId): ?string
    {
        if (! $realmId) {
            return null;
        }

        return Str::mask($realmId, '*', 0, max(0, strlen($realmId) - 4));
    }
}
