<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check user token
$user = \App\Models\Central\User::where('email', 'shipper@cyclos.ai')->first();
echo "User: {$user->email}\n";
echo "Has remember_token: " . ($user->remember_token ? 'YES (' . strlen($user->remember_token) . ' chars)' : 'NO') . "\n";

// Simulate a login to get a fresh token
$token = \Illuminate\Support\Str::random(64);
$user->forceFill([
    'remember_token' => hash('sha256', $token),
    'last_login_at' => now(),
])->save();

echo "Fresh token: {$token}\n";
echo "Stored hash: {$user->remember_token}\n";

// Now test the guard
$request = \Illuminate\Http\Request::create('/api/v1/containers', 'GET');
$request->headers->set('Authorization', 'Bearer ' . $token);
$request->headers->set('Accept', 'application/json');

$app->instance('request', $request);

$guard = \Illuminate\Support\Facades\Auth::guard('api');
$authedUser = $guard->user();

if ($authedUser) {
    echo "AUTH SUCCESS: {$authedUser->email} (id: {$authedUser->id})\n";
} else {
    echo "AUTH FAILED: guard returned null\n";
}
