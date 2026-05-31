<?php

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

Route::get('/{any}', function () {
    return view('app');
})->where('any', '^(?!api/|portal/).*$')->name('spa');
