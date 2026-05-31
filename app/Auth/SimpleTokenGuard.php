<?php

namespace App\Auth;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

/**
 * A lightweight Bearer-token guard that validates tokens stored as
 * SHA-256 hashes in the user's `remember_token` column.
 *
 * The AuthController::login() method issues Str::random(64) tokens
 * and persists hash('sha256', $token) in remember_token.  This guard
 * reverses that lookup on every authenticated request.
 */
class SimpleTokenGuard implements Guard
{
    use GuardHelpers;

    protected Request $request;

    public function __construct(UserProvider $provider, Request $request)
    {
        $this->provider = $provider;
        $this->request  = $request;
    }

    /**
     * Get the currently authenticated user.
     */
    public function user()
    {
        if (! is_null($this->user)) {
            return $this->user;
        }

        $token = $this->getBearerToken();

        if ($token) {
            $hashedToken = hash('sha256', $token);

            $user = $this->provider
                ->getModel()::where('remember_token', $hashedToken)
                ->first();

            if ($user && $this->tokenIsExpired($user)) {
                return null;
            }

            $this->user = $user;
        }

        return $this->user;
    }

    /**
     * Determine whether the user's token has exceeded the configured TTL.
     */
    protected function tokenIsExpired($user): bool
    {
        if (is_null($user->token_created_at)) {
            return false;
        }

        $ttlDays = (int) config('auth.token_ttl', 30);

        return $user->token_created_at->addDays($ttlDays)->isPast();
    }

    /**
     * Validate a user's credentials (used by Auth::validate()).
     */
    public function validate(array $credentials = []): bool
    {
        if (empty($credentials['email']) || empty($credentials['password'])) {
            return false;
        }

        $user = $this->provider->retrieveByCredentials($credentials);

        return $user && $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * Extract the Bearer token from the Authorization header.
     */
    protected function getBearerToken(): ?string
    {
        return $this->request->bearerToken();
    }
}
