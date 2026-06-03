<?php

namespace App\Console\Commands\Tenant;

use App\Models\Central\Tenant;
use App\Models\Tenant\Container;
use App\Models\Tenant\Vessel;
use App\Services\Vessel\VesselLinkingService;
use Illuminate\Console\Command;

class LinkContainerVesselsCommand extends Command
{
    protected $signature = 'containers:link-vessels
                            {--tenant= : Run for a specific tenant ID only}
                            {--dry-run : Show what would be linked without making changes}';

    protected $description = 'Link containers to vessel records so they appear on the global vessel map';

    public function handle(VesselLinkingService $vesselLinkingService): int
    {
        $tenantId = $this->option('tenant');
        $dryRun   = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written.');
        }

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

        $this->info("Processing {$tenants->count()} tenant(s)...");

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($tenant, $vesselLinkingService, $dryRun) {
                $this->processTenant($tenant->id, $vesselLinkingService, $dryRun);
            });
        }

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function processTenant(string $tenantId, VesselLinkingService $vesselLinkingService, bool $dryRun): void
    {
        $this->line("  Tenant: {$tenantId}");

        // Ensure the canonical demo vessel exists (MSC RIKKU, IMO 9839284)
        if (! $dryRun) {
            $this->ensureDemoVessel($tenantId);
        }

        // Find containers without a vessel_id that carry vessel-identifying info.
        // We look at:
        //   1. The container's own shipping_line / carrier_scac columns
        //   2. The container's MBL vessel_id (copy the parent vessel reference)
        $containers = Container::whereNull('vessel_id')
            ->with('mbl.vessel')
            ->get();

        $linked  = 0;
        $skipped = 0;

        foreach ($containers as $container) {
            // Strategy 1: inherit vessel from parent MBL
            if ($container->mbl && $container->mbl->vessel_id) {
                if (! $dryRun) {
                    $container->vessel_id = $container->mbl->vessel_id;
                    $container->save();
                }

                $this->line("    [MBL inherit] {$container->container_number} → vessel {$container->mbl->vessel_id}");
                $linked++;
                continue;
            }

            // Strategy 2: link via shipping_line / carrier_scac stored on the container
            $scac = $container->carrier_scac ?? $container->shipping_line ?? null;

            if (! $scac) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("    [would link] {$container->container_number} via carrier_scac={$scac}");
                $linked++;
                continue;
            }

            $vessel = $vesselLinkingService->findOrCreateVessel([
                'vessel_name'  => null,
                'imo'          => null,
                'carrier_scac' => $scac,
            ]);

            if ($vessel) {
                $container->vessel_id = $vessel->id;
                $container->save();
                $this->line("    [linked] {$container->container_number} → vessel {$vessel->id}");
                $linked++;
            } else {
                $skipped++;
            }
        }

        $this->line("    Linked: {$linked}  |  Skipped (no vessel info): {$skipped}");
    }

    /**
     * Ensure the seeded demo vessel (MSC RIKKU, IMO 9839284) exists so the
     * ten demo containers can be linked to it and appear on the map.
     */
    private function ensureDemoVessel(string $organizationId): void
    {
        $existing = Vessel::where('imo_number', '9839284')->first();

        if ($existing) {
            return;
        }

        Vessel::create([
            'organization_id' => $organizationId,
            'name'            => 'MSC RIKKU',
            'imo_number'      => '9839284',
            'carrier_scac'    => 'MSCU',
            'vessel_type'     => 'Container Ship',
        ]);

        $this->line("    Created demo vessel: MSC RIKKU (IMO 9839284)");

        // Link any containers whose shipping_line is MSC
        Container::whereNull('vessel_id')
            ->where(function ($q) {
                $q->where('carrier_scac', 'MSCU')
                  ->orWhere('carrier_scac', 'MEDU')
                  ->orWhere('shipping_line', 'MSC');
            })
            ->each(function (Container $container) {
                $vessel = Vessel::where('imo_number', '9839284')->first();
                if ($vessel) {
                    $container->vessel_id = $vessel->id;
                    $container->save();
                    $this->line("    [MSC demo] linked {$container->container_number}");
                }
            });
    }
}
