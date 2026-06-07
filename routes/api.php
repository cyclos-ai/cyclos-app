<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Billing\BillingController;
use App\Http\Controllers\Api\V1\Billing\StripeWebhookController;
use App\Http\Controllers\Api\V1\User\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (Central / Public)
|--------------------------------------------------------------------------
| Auth endpoint and user-profile routes that do not require tenant context.
| Tenant-scoped routes are registered in routes/tenant.php by stancl/tenancy.
*/

// OAuth2 token endpoint — throttled separately from API calls
Route::post('/auth/token', [AuthController::class, 'postToken'])
    ->middleware('throttle:auth')
    ->name('auth.token');

// SPA auth routes (no tenant context required)
Route::prefix('v1/auth')->name('v1.auth.')->group(function () {
    Route::post('/login',           [AuthController::class, 'login'])->name('login');
    Route::post('/logout',          [AuthController::class, 'logout'])->name('logout');
    Route::get('/me',               [AuthController::class, 'me'])->name('me');
    Route::post('/register',        [\App\Http\Controllers\Api\V1\Auth\RegistrationController::class, 'register'])->name('register');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('/reset-password',  [AuthController::class, 'resetPassword'])->name('reset-password');
});

// Authenticated user routes (no tenant context required)
Route::middleware(['auth:api'])
    ->prefix('v1')
    ->name('v1.')
    ->group(function () {
        Route::get('/users/me',           [UserController::class, 'profile'])->name('users.me');
        Route::put('/users/me',           [UserController::class, 'updateProfile'])->name('users.me.update');
        Route::put('/users/me/password',  [UserController::class, 'changePassword'])->name('users.me.password');
        Route::get('/users/supported-scacs', [UserController::class, 'supportedScacs'])->name('users.supported-scacs');

        // Billing (central / per-tenant)
        Route::prefix('billing')->name('billing.')->group(function () {
            Route::get('/plans',     [BillingController::class, 'plans'])->name('plans');
            Route::get('/current',   [BillingController::class, 'current'])->name('current');
            Route::post('/checkout', [BillingController::class, 'checkout'])->name('checkout');
            Route::post('/portal',   [BillingController::class, 'portal'])->name('portal');
        });
    });

// Stripe webhook (no auth — verified by signature)
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');

// Carrier inbound webhooks (no auth — validated by HMAC signature)
Route::post('/v1/carrier-webhooks/{scac}', [\App\Http\Controllers\Api\V1\Carrier\CarrierWebhookController::class, 'receive'])
    ->middleware('throttle:60,1')
    ->name('v1.carrier-webhooks.receive');

// ----------------------------------------------------------------
// Public Carrier Onboarding (no auth — carriers use invite token)
// ----------------------------------------------------------------
Route::prefix('v1/carrier-onboard')->name('v1.carrier-onboard.')->group(function () {
    // Static lookup routes MUST come before {tenant_slug}/{token} to avoid matching "lookup-scac" as a tenant slug
    Route::get('lookup-scac/{scac}',   [\App\Http\Controllers\Api\V1\Carrier\CarrierOnboardingController::class, 'lookupScac'])->name('lookup-scac');
    Route::get('lookup-usdot/{usdot}', [\App\Http\Controllers\Api\V1\Carrier\CarrierOnboardingController::class, 'lookupUsdot'])->name('lookup-usdot');
    Route::get('{tenant_slug}/{token}',              [\App\Http\Controllers\Api\V1\Carrier\CarrierOnboardingController::class, 'validateToken'])->name('validate');
    Route::post('{tenant_slug}/{token}/complete',    [\App\Http\Controllers\Api\V1\Carrier\CarrierOnboardingController::class, 'completeOnboarding'])->name('complete');
});

// Admin onboarding routes
Route::middleware(['auth:api'])
    ->prefix('v1/admin')
    ->name('v1.admin.')
    ->group(function () {
        Route::get('/registrations',                   [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'index'])->name('registrations.index');
        Route::get('/registrations/{uuid}',            [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'show'])->name('registrations.show');
        Route::post('/registrations/{uuid}/approve',   [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'approve'])->name('registrations.approve');
        Route::post('/registrations/{uuid}/reject',    [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'reject'])->name('registrations.reject');
        Route::post('/invite',                         [\App\Http\Controllers\Api\V1\Admin\OnboardingController::class, 'invite'])->name('invite');
    });
