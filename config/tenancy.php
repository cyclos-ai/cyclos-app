<?php

declare(strict_types=1);

use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Database\Models\Tenant;

return [
    'tenant_model' => \App\Models\Central\Tenant::class,
    'domain_model' => Domain::class,

    'central_domains' => explode(',', env('TENANCY_CENTRAL_DOMAINS', 'localhost')),

    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,

    'database' => [
        'template_tenant_connection' => 'tenant',
        'prefix' => 'tenant_',
        'suffix' => '',
        'managers' => [
            'mysql' => Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager::class,
        ],
    ],

    'cache' => [
        'tag_base' => 'tenant_',
    ],

    'filesystem' => [
        'suffix_base' => 'tenant_',
        'disks' => [
            'local',
            'public',
            's3',
        ],
        'root_override' => [
            'local' => '%storage_path%/app/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
    ],

    'redis' => [
        'prefix_base' => 'tenant_',
        'prefixed_connections' => [
            'default',
            'cache',
        ],
    ],

    'features' => [
        Stancl\Tenancy\Features\UserImpersonation::class,
    ],

    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'Database\\Seeders\\TenantDatabaseSeeder',
    ],

    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\RedisTenancyBootstrapper::class,
    ],
];
