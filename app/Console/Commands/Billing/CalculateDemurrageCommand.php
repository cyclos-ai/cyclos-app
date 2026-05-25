<?php

namespace App\Console\Commands\Billing;

use App\Jobs\Demurrage\CalculateDemurrageJob;
use App\Jobs\Demurrage\CalculateDetentionJob;
use App\Models\Central\Tenant;
use Illuminate\Console\Command;

class CalculateDemurrageCommand extends Command
{
    protected $signature = 'billing:calculate-demurrage
                            {--tenant= : Run for a specific tenant ID only}
                            {--detention : Also calculate detention charges}';

    protected $description = 'Calculate demurrage (and optionally detention) charges for all tenants';

    public function handle(): int
    {
        $tenantId  = $this->option('tenant');
        $detention = $this->option('detention');

        if ($tenantId !== null) {
            $tenant = Tenant::find($tenantId);

            if ($tenant === null) {
                $this->error("Tenant '{$tenantId}' not found.");
                return self::FAILURE;
            }

            $tenants = collect([$tenant]);
        } else {
            $tenants = Tenant::all();
        }

        $this->info("Dispatching demurrage calculation for {$tenants->count()} tenant(s)...");

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($tenant, $detention) {
                CalculateDemurrageJob::dispatch();
                $this->line("  Demurrage job dispatched for tenant: {$tenant->id}");

                if ($detention) {
                    CalculateDetentionJob::dispatch();
                    $this->line("  Detention job dispatched for tenant: {$tenant->id}");
                }
            });
        }

        $this->info('All jobs dispatched successfully.');

        return self::SUCCESS;
    }
}
