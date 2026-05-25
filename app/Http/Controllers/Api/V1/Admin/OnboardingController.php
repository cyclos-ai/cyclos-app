<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Domain\User\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Central\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OnboardingController extends Controller
{
    /**
     * GET /api/v1/admin/registrations
     * List pending registration requests, paginated.
     */
    public function index(Request $request): JsonResponse
    {
        $pending = User::where('approval_status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return response()->json($pending);
    }

    /**
     * GET /api/v1/admin/registrations/{uuid}
     * Show a single registration request.
     */
    public function show(string $uuid): JsonResponse
    {
        $user = User::findOrFail($uuid);

        return response()->json($user);
    }

    /**
     * POST /api/v1/admin/registrations/{uuid}/approve
     * Approve a pending registration.
     */
    public function approve(Request $request, string $uuid): JsonResponse
    {
        $user = User::findOrFail($uuid);

        $user->update([
            'approval_status' => 'approved',
            'is_active'       => true,
            'approved_by'     => $request->user()->id,
            'approved_at'     => now(),
        ]);

        return response()->json([
            'message' => 'Registration approved successfully.',
            'uuid'    => $user->id,
        ]);
    }

    /**
     * POST /api/v1/admin/registrations/{uuid}/reject
     * Reject a pending registration.
     */
    public function reject(Request $request, string $uuid): JsonResponse
    {
        $user = User::findOrFail($uuid);

        $user->update([
            'approval_status'  => 'rejected',
            'rejection_reason' => $request->input('rejection_reason'),
        ]);

        return response()->json([
            'message' => 'Registration rejected.',
            'uuid'    => $user->id,
        ]);
    }

    /**
     * POST /api/v1/admin/invite
     * Invite a user directly (pre-approved, active).
     */
    public function invite(Request $request): JsonResponse
    {
        $validRoles = array_column(UserRole::cases(), 'value');

        $validator = Validator::make($request->all(), [
            'first_name'   => 'required|string|max:255',
            'last_name'    => 'required|string|max:255',
            'email'        => 'required|email|unique:central.users,email',
            'role'         => 'required|string|in:' . implode(',', $validRoles),
            'company_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $tempPassword = Str::random(12);

        $user = User::create([
            'first_name'      => $request->first_name,
            'last_name'       => $request->last_name,
            'email'           => $request->email,
            'password'        => Hash::make($tempPassword),
            'role'            => $request->role,
            'company_name'    => $request->company_name,
            'is_active'       => true,
            'approval_status' => 'approved',
            'approved_by'     => $request->user()->id,
            'approved_at'     => now(),
            'tenant_id'       => null,
        ]);

        return response()->json([
            'message'       => 'User invited successfully.',
            'user'          => [
                'uuid'         => $user->id,
                'first_name'   => $user->first_name,
                'last_name'    => $user->last_name,
                'email'        => $user->email,
                'role'         => $user->role,
                'company_name' => $user->company_name,
            ],
            'temp_password' => $tempPassword,
        ], 201);
    }
}
