<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Central\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     * Simple token-based login for the SPA.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account is disabled'], 403);
        }

        if ($user->approval_status === 'pending') {
            return response()->json(['message' => 'Your registration is pending admin approval.'], 403);
        }

        if ($user->approval_status === 'rejected') {
            return response()->json(['message' => 'Your registration has been declined.'], 403);
        }

        // Generate a simple token and store it
        $token = Str::random(64);
        $user->forceFill([
            'remember_token' => hash('sha256', $token),
            'last_login_at'  => now(),
        ])->save();

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => [
                'uuid'              => $user->id,
                'first_name'        => $user->first_name,
                'last_name'         => $user->last_name,
                'email'             => $user->email,
                'role'              => $user->role,
                'phone'             => $user->phone,
                'avatar_url'        => $user->avatar_url,
                'timezone'          => $user->timezone,
                'tenant_id'         => $user->tenant_id,
                'organization_uuid' => $user->tenant_id,
                'approval_status'   => $user->approval_status,
            ],
        ]);
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(Request $request): JsonResponse
    {
        if ($request->user()) {
            $request->user()->forceFill(['remember_token' => null])->save();
        }

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * GET /api/v1/auth/me
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json([
            'uuid'              => $user->id,
            'first_name'        => $user->first_name,
            'last_name'         => $user->last_name,
            'email'             => $user->email,
            'role'              => $user->role,
            'phone'             => $user->phone,
            'avatar_url'        => $user->avatar_url,
            'timezone'          => $user->timezone,
            'tenant_id'         => $user->tenant_id,
            'organization_uuid' => $user->tenant_id,
            'approval_status'   => $user->approval_status,
        ]);
    }

    /**
     * POST /api/auth/token
     * Generate OAuth2 Bearer token (Password Grant or Client Credentials).
     */
    public function postToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'grant_type'    => 'required|in:password,client_credentials,refresh_token',
            'client_id'     => 'required|string',
            'client_secret' => 'required|string',
            'username'      => 'required_if:grant_type,password|email|nullable',
            'password'      => 'required_if:grant_type,password|string|nullable',
            'scope'         => 'nullable|string',
            'refresh_token' => 'required_if:grant_type,refresh_token|string|nullable',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $tokenRequest = Request::create('/oauth/token', 'POST', $request->all());

        try {
            $response = app()->handle($tokenRequest);
            $content  = json_decode($response->getContent(), true);

            if ($response->getStatusCode() !== 200) {
                return response()->json($content, $response->getStatusCode());
            }

            return response()->json([
                'message'       => 'Token generated successfully',
                'access_token'  => $content['access_token'],
                'token_type'    => $content['token_type'] ?? 'Bearer',
                'expires_in'    => $content['expires_in'] ?? null,
                'refresh_token' => $content['refresh_token'] ?? null,
                'scope'         => $content['scope'] ?? '',
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to generate token: ' . $e->getMessage()], 500);
        }
    }
}
