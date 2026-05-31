<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Check domains table
$domains = \Illuminate\Support\Facades\DB::connection('mysql')->table('domains')->get();
echo "=== Domains ===\n";
foreach ($domains as $d) {
    echo json_encode($d) . "\n";
}

// Check tenants table
$tenants = \Illuminate\Support\Facades\DB::connection('mysql')->table('tenants')->get();
echo "\n=== Tenants ===\n";
foreach ($tenants as $t) {
    echo json_encode($t) . "\n";
}

// Check tenancy config
$config = config('tenancy.identification_middleware');
echo "\n=== Identification Middleware ===\n";
echo json_encode($config) . "\n";

echo "\n=== Central Domains Config ===\n";
echo json_encode(config('tenancy.central_domains')) . "\n";
