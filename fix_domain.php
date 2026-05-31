<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// The InitializeTenancyBySubdomain middleware extracts "demo" from "demo.localhost"
// and looks it up in the domains table. Currently stored as "localhost" — fix it to "demo".
$updated = \Illuminate\Support\Facades\DB::connection('mysql')
    ->table('domains')
    ->where('tenant_id', 'demo')
    ->where('domain', 'localhost')
    ->update(['domain' => 'demo']);

echo "Updated {$updated} domain record(s)\n";

// Verify
$domains = \Illuminate\Support\Facades\DB::connection('mysql')->table('domains')->get();
foreach ($domains as $d) {
    echo "Domain: {$d->domain} => tenant: {$d->tenant_id}\n";
}
