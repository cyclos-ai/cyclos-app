<?php

namespace App\Http\Middleware;

use App\Domain\User\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$groups): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $role = UserRole::tryFrom($user->role ?? '');

        if (!$role) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // super_admin always passes
        if ($role === UserRole::SUPER_ADMIN) {
            return $next($request);
        }

        foreach ($groups as $group) {
            if ($group === 'shipper' && $role->isShipper()) {
                return $next($request);
            }
            if ($group === 'drayage' && $role->isDrayage()) {
                return $next($request);
            }
            if ($group === 'admin' && $role === UserRole::SUPER_ADMIN) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'Forbidden. Insufficient role.'], 403);
    }
}
