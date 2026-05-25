<?php

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ChangePasswordRequest;
use App\Http\Requests\User\UpdateProfileRequest;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * GET /api/v1/users/me
     */
    public function profile(Request $request): JsonResponse
    {
        return $this->success(new UserResource($request->user()));
    }

    /**
     * PUT /api/v1/users/me
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return $this->success(new UserResource($user->fresh()), 'Profile updated');
    }

    /**
     * PUT /api/v1/users/me/password
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            return $this->error('Current password is incorrect', 422);
        }

        $user->update(['password' => Hash::make($request->input('new_password'))]);

        return $this->success(null, 'Password changed successfully');
    }

    /**
     * GET /api/v1/users/supported-scacs
     */
    public function supportedScacs(Request $request): JsonResponse
    {
        $scacs = [
            ['scac' => 'MAEU', 'name' => 'Maersk'],
            ['scac' => 'MSCU', 'name' => 'MSC'],
            ['scac' => 'CMDU', 'name' => 'CMA CGM'],
            ['scac' => 'COSU', 'name' => 'COSCO'],
            ['scac' => 'HLCU', 'name' => 'Hapag-Lloyd'],
            ['scac' => 'EGLV', 'name' => 'Evergreen'],
            ['scac' => 'YMLU', 'name' => 'Yang Ming'],
            ['scac' => 'ONEY', 'name' => 'ONE'],
            ['scac' => 'APLU', 'name' => 'APL'],
            ['scac' => 'ZIMU', 'name' => 'ZIM'],
            ['scac' => 'WHLC', 'name' => 'Wan Hai'],
            ['scac' => 'SMLU', 'name' => 'SM Line'],
            ['scac' => 'ANNU', 'name' => 'ANL'],
            ['scac' => 'HDMU', 'name' => 'HMM'],
        ];

        return $this->success($scacs);
    }
}
