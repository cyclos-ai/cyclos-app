<?php

namespace App\Http\Controllers\Api\V1\Carrier;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Models\Tenant\CarrierInvite;
use App\Models\Tenant\DrayageCarrier;
use App\Services\Carrier\FmcsaLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CarrierOnboardingController extends Controller
{
    public function __construct(
        private readonly FmcsaLookupService $fmcsaService,
    ) {}

    // ================================================================
    // SHIPPER-SIDE (authenticated, tenant context already initialized)
    // ================================================================

    /**
     * GET /api/v1/carrier-onboarding/carriers
     * List all drayage carriers connected to this organization.
     */
    public function carriers(Request $request): JsonResponse
    {
        $query = DrayageCarrier::query();

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $this->applySorting(
            $query,
            $request->input('order_by', 'company_name'),
            (int) $request->input('direction', 1)
        );

        return $this->paginate($query, $request);
    }

    /**
     * GET /api/v1/carrier-onboarding/carriers/{uuid}
     */
    public function showCarrier(string $uuid): JsonResponse
    {
        $carrier = DrayageCarrier::find($uuid);

        if (! $carrier) {
            return $this->notFound('Drayage carrier not found.');
        }

        return $this->success($carrier);
    }

    /**
     * POST /api/v1/carrier-onboarding/invites
     * Create a new carrier invite link.
     */
    public function createInvite(Request $request): JsonResponse
    {
        $request->validate([
            'email'        => 'nullable|email',
            'company_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();

        $invite = CarrierInvite::create([
            'organization_id' => $user->organization_id ?? $request->header('X-Organization-Id'),
            'email'           => $request->input('email'),
            'company_name'    => $request->input('company_name'),
            'invited_by'      => $user->id,
        ]);

        // Build the public onboarding URL with tenant slug
        $tenant = tenancy()->tenant;
        $tenantSlug = $tenant->slug ?? $tenant->id;
        $invite->setAttribute('onboarding_url', url("/carrier/onboard/{$tenantSlug}/{$invite->token}"));

        return $this->created($invite, 'Carrier invite created.');
    }

    /**
     * GET /api/v1/carrier-onboarding/invites
     * List all invites for this organization.
     */
    public function invites(Request $request): JsonResponse
    {
        $query = CarrierInvite::query()
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        return $this->paginate($query, $request);
    }

    /**
     * DELETE /api/v1/carrier-onboarding/invites/{uuid}
     * Revoke a pending invite.
     */
    public function revokeInvite(string $uuid): JsonResponse
    {
        $invite = CarrierInvite::find($uuid);

        if (! $invite) {
            return $this->notFound('Invite not found.');
        }

        if ($invite->status !== 'pending') {
            return $this->error('Only pending invites can be revoked.', 422);
        }

        $invite->update(['status' => 'revoked']);

        return $this->success(null, 'Invite revoked.');
    }

    // ================================================================
    // PUBLIC ENDPOINTS (no auth — carriers use invite link)
    // These methods manually initialize tenancy from the tenant slug.
    // ================================================================

    /**
     * GET /api/v1/carrier-onboard/{tenant_slug}/{token}
     * Validate an invite token and return invite details.
     */
    public function validateToken(string $tenantSlug, string $token): JsonResponse
    {
        if (! $this->initializeTenantBySlug($tenantSlug)) {
            return $this->notFound('Invalid invite link.');
        }

        $invite = CarrierInvite::where('token', $token)->first();

        if (! $invite) {
            return $this->notFound('Invalid or expired invite link.');
        }

        if (! $invite->isUsable()) {
            $reason = $invite->isExpired()
                ? 'This invite has expired.'
                : 'This invite has already been used.';

            return $this->error($reason, 410);
        }

        return $this->success([
            'invite_id'    => $invite->id,
            'email'        => $invite->email,
            'company_name' => $invite->company_name,
            'expires_at'   => $invite->expires_at->toIso8601String(),
            'tenant_name'  => tenancy()->tenant->name ?? $tenantSlug,
        ]);
    }

    /**
     * GET /api/v1/carrier-onboard/lookup-scac/{scac}
     * Public SCAC lookup — carrier enters SCAC, we auto-populate data.
     */
    public function lookupScac(string $scac): JsonResponse
    {
        $result = $this->fmcsaService->lookupByScac($scac);

        if (! $result) {
            return $this->success([
                'found'   => false,
                'scac'    => strtoupper($scac),
                'message' => 'No carrier data found for this SCAC. You can still complete registration manually.',
            ]);
        }

        return $this->success([
            'found'   => true,
            'carrier' => $result,
        ]);
    }

    /**
     * GET /api/v1/carrier-onboard/lookup-usdot/{usdot}
     * Public USDOT lookup — alternative lookup method.
     */
    public function lookupUsdot(string $usdot): JsonResponse
    {
        $result = $this->fmcsaService->lookupByUsdot($usdot);

        if (! $result) {
            return $this->success([
                'found'   => false,
                'usdot'   => $usdot,
                'message' => 'No carrier data found for this USDOT number.',
            ]);
        }

        return $this->success([
            'found'   => true,
            'carrier' => $result,
        ]);
    }

    /**
     * POST /api/v1/carrier-onboard/{tenant_slug}/{token}/complete
     * Complete carrier onboarding — creates the DrayageCarrier record.
     */
    public function completeOnboarding(Request $request, string $tenantSlug, string $token): JsonResponse
    {
        if (! $this->initializeTenantBySlug($tenantSlug)) {
            return $this->notFound('Invalid invite link.');
        }

        $invite = CarrierInvite::where('token', $token)->first();

        if (! $invite) {
            return $this->notFound('Invalid invite link.');
        }

        if (! $invite->isUsable()) {
            $reason = $invite->isExpired()
                ? 'This invite has expired.'
                : 'This invite has already been used.';

            return $this->error($reason, 410);
        }

        $request->validate([
            'company_name'     => 'required|string|max:255',
            'scac'             => 'required|string|max:10',
            'usdot'            => 'nullable|string|max:20',
            'mc_number'        => 'nullable|string|max:20',
            'contact_name'     => 'required|string|max:255',
            'contact_email'    => 'required|email',
            'contact_phone'    => 'nullable|string|max:20',
            'address'          => 'nullable|string|max:500',
            'city'             => 'nullable|string|max:100',
            'state'            => 'nullable|string|max:2',
            'zip'              => 'nullable|string|max:10',
            'fleet_size'       => 'nullable|integer|min:1',
            'equipment_types'  => 'nullable|array',
            'equipment_types.*' => 'string|in:flatbed,container,reefer,tanker,dryvan',
            'service_areas'    => 'nullable|array',
            'service_areas.*'  => 'string|max:2',
        ]);

        $scac = strtoupper($request->input('scac'));

        // Check if this SCAC is already connected to this organization
        $existing = DrayageCarrier::where('organization_id', $invite->organization_id)
            ->where('scac', $scac)
            ->first();

        if ($existing) {
            return $this->error(
                "A carrier with SCAC '{$scac}' is already connected to this organization.",
                409
            );
        }

        // Create the drayage carrier record
        $carrier = DrayageCarrier::create([
            'organization_id' => $invite->organization_id,
            'company_name'    => $request->input('company_name'),
            'scac'            => $scac,
            'usdot'           => $request->input('usdot'),
            'mc_number'       => $request->input('mc_number'),
            'contact_name'    => $request->input('contact_name'),
            'contact_email'   => $request->input('contact_email'),
            'contact_phone'   => $request->input('contact_phone'),
            'address'         => $request->input('address'),
            'city'            => $request->input('city'),
            'state'           => $request->input('state'),
            'zip'             => $request->input('zip'),
            'fleet_size'      => $request->input('fleet_size'),
            'equipment_types' => $request->input('equipment_types', []),
            'service_areas'   => $request->input('service_areas', []),
            'status'          => 'active',
        ]);

        // Mark the invite as accepted
        $invite->update([
            'status'             => 'accepted',
            'accepted_at'        => now(),
            'drayage_carrier_id' => $carrier->id,
        ]);

        return $this->created([
            'carrier' => $carrier,
            'message' => 'Carrier onboarding completed successfully. You are now connected with the shipper.',
        ]);
    }

    // ================================================================
    // Helpers
    // ================================================================

    /**
     * Resolve a tenant by slug or ID and initialize tenancy.
     */
    private function initializeTenantBySlug(string $slug): bool
    {
        $tenant = Tenant::where('slug', $slug)->orWhere('id', $slug)->first();

        if (! $tenant) {
            return false;
        }

        tenancy()->initialize($tenant);

        return true;
    }
}
