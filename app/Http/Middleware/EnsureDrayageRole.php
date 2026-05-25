<?php

namespace App\Http\Middleware;

use App\Domain\User\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDrayageRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $role = UserRole::tryFrom($user->role ?? '');

        if (!$role || (!$role->isDrayage() && $role !== UserRole::SUPER_ADMIN)) {
            return response()->json([
                'message' => 'Forbidden. Drayage role required.',
            ], 403);
        }

        return $next($request);
    }
}
