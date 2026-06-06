<?php

use App\Http\Controllers\Api\V1\Integration\QuickBooksIntegrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Catch-all route to serve the Vue SPA. All non-API routes return the
| app view; Vue Router handles client-side routing from there.
*/

// Serve standalone portal pages (bypass SPA catch-all)
Route::get('/portal/{file}', function (string $file) {
    $path = public_path("portal/{$file}");
    if (! file_exists($path)) {
        abort(404);
    }
    return response()->file($path, ['Content-Type' => 'text/html']);
})->where('file', '[a-z0-9\-]+\.html')->name('portal');

/*
|--------------------------------------------------------------------------
| QuickBooks Online OAuth Callback
|--------------------------------------------------------------------------
| Intuit redirects the browser here (on the tenant subdomain) after the
| user authorizes the connection. Tenancy is initialised by subdomain;
| no Bearer token is required. Must be registered before the SPA catch-all.
*/
Route::get('/quickbooks/callback', [QuickBooksIntegrationController::class, 'callback'])
    ->middleware([
        \App\Http\Middleware\InitializeTenancyBySubdomainOrHeader::class,
        \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
    ])
    ->name('quickbooks.callback');

Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api/|portal/|quickbooks/callback).*$')->name('spa');
