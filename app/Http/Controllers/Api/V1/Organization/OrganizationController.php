<?php

namespace App\Http\Controllers\Api\V1\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Http\Resources\Organization\OrganizationResource;
use App\Models\Tenant\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    /**
     * GET /api/v1/organizations/current
     */
    public function show(Request $request): JsonResponse
    {
        $org = Organization::first();

        if (! $org) {
            return $this->notFound('Organization not found');
        }

        return $this->success(new OrganizationResource($org));
    }

    /**
     * PUT /api/v1/organizations/current
     */
    public function update(UpdateOrganizationRequest $request): JsonResponse
    {
        $org = Organization::first();

        if (! $org) {
            return $this->notFound('Organization not found');
        }

        $org->update($request->validated());

        return $this->success(new OrganizationResource($org->fresh()), 'Organization updated');
    }

    /**
     * GET /api/v1/organizations/members
     */
    public function members(Request $request): JsonResponse
    {
        $org = Organization::first();

        if (! $org) {
            return $this->notFound('Organization not found');
        }

        $query = $org->users()->getQuery();

        $this->applySorting(
            $query,
            $request->input('order_by', 'name'),
            (int) $request->input('direction', 1)
        );

        return $this->paginate($query, $request);
    }

    /**
     * POST /api/v1/organizations/members
     */
    public function inviteMember(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|max:255',
            'role'  => 'required|string|in:admin,manager,viewer,operator',
            'name'  => 'nullable|string|max:255',
        ]);

        // In production this would create an invitation record and send email
        return $this->success([
            'email'  => $request->input('email'),
            'role'   => $request->input('role'),
            'status' => 'invited',
        ], 'Invitation sent');
    }

    /**
     * DELETE /api/v1/organizations/members/{uuid}
     */
    public function removeMember(string $uuid): JsonResponse
    {
        $org = Organization::first();

        if (! $org) {
            return $this->notFound('Organization not found');
        }

        $user = $org->users()->where('uuid', $uuid)->first();

        if (! $user) {
            return $this->notFound('Member not found');
        }

        $org->users()->detach($user->id);

        return $this->noContent();
    }

    /**
     * PUT /api/v1/organizations/sso
     */
    public function updateSso(Request $request): JsonResponse
    {
        $org = Organization::first();

        if (! $org) {
            return $this->notFound('Organization not found');
        }

        $request->validate([
            'sso_enabled'   => 'required|boolean',
            'sso_provider'  => 'required_if:sso_enabled,true|nullable|in:google,microsoft,okta,saml',
            'sso_domain'    => 'required_if:sso_enabled,true|nullable|string|max:255',
            'sso_config'    => 'nullable|array',
        ]);

        $org->update([
            'sso_enabled'  => $request->boolean('sso_enabled'),
            'sso_provider' => $request->input('sso_provider'),
            'sso_domain'   => $request->input('sso_domain'),
            'sso_config'   => $request->input('sso_config'),
        ]);

        return $this->success(new OrganizationResource($org->fresh()), 'SSO settings updated');
    }
}
