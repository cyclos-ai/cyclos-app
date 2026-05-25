<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| Catch-all route to serve the Vue SPA. All non-API routes return the
| app view; Vue Router handles client-side routing from there.
*/

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*')->name('spa');
