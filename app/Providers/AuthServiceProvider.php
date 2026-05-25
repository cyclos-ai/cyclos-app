<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot(): void
    {
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
