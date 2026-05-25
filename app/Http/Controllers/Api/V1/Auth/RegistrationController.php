<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Central\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class RegistrationController extends Controller
{
    /**
     * POST /api/v1/auth/register
     * Public self-registration endpoint. Creates a pending user awaiting admin approval.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name'            => 'required|string|max:255',
            'last_name'             => 'required|string|max:255',
            'email'                 => 'required|email|unique:central.users,email',
            'password'              => 'required|string|min:8|confirmed',
            'role'                  => 'required|string|in:shipper_user,drayage_dispatcher',
            'company_name'          => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'first_name'      => $request->first_name,
            'last_name'       => $request->last_name,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'role'            => $request->role,
            'company_name'    => $request->company_name,
            'is_active'       => false,
            'approval_status' => 'pending',
            'tenant_id'       => null,
        ]);

        return response()->json([
            'message' => 'Registration submitted successfully. Your account is pending admin approval. You will be notified once your account has been reviewed.',
            'uuid'    => $user->id,
        ], 201);
    }
}
