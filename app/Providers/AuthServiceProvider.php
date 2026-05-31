<?php

namespace App\Providers;

use App\Auth\SimpleTokenGuard;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot(): void
    {
        // Register the simple-token guard driver used by the SPA login flow.
        // AuthController::login() issues Str::random(64) tokens and stores
        // the SHA-256 hash in remember_token; this guard reverses the lookup.
        Auth::extend('simple-token', function ($app, $name, array $config) {
            $provider = Auth::createUserProvider($config['provider']);

            return new SimpleTokenGuard($provider, $app['request']);
        });

        // Passport is still available for the OAuth /oauth/token flow
        // (external API consumers, client_credentials grants, etc.).
        Passport::tokensExpireIn(now()->addDays(15));
        Passport::refreshTokensExpireIn(now()->addDays(30));
        Passport::personalAccessTokensExpireIn(now()->addMonths(6));

        Passport::tokensCan([
            'read' => 'Read access to resources',
            'write' => 'Write access to resources',
            'admin' => 'Full administrative access',
            'tracking' => 'Access to tracking endpoints',
            'billing' => 'Access to billing and invoice endpoints',
            'webhook' => 'Webhook management',
        ]);
    }
}
