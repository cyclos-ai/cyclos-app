<?php

namespace App\Console\Commands\Tracking;

use App\Models\Central\Tenant;
use App\Models\Tenant\Container;
use App\Services\JsonCargo\JsonCargoService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Refresh container tracking from JSONCargo and PERSIST it to each container.
 *
 * The app's native carrier-tracker path (ContainerTrackingService) relies on
 * per-carrier scrapers; JSONCargo (the aggregator that actually returns data)
 * was only wired for read-only lookups. This command bridges that gap so
 * container status/ETA stay fresh automatically (scheduled in routes/console.php).
 */
class RefreshJsonCargoCommand extends Command
{
    protected $signature = 'tracking:refresh-jsoncargo {--tenant= : Limit to a single tenant id}';

    protected $description = 'Refresh container status/ETA from JSONCargo and persist it per tenant';

    public function handle(): int
    {
        $tenants = $this->option('tenant')
            ? Tenant::where('id', $this->option('tenant'))->get()
            : Tenant::all();

        $grandTotal = 0;

        foreach ($tenants as $tenant) {
            $updated = 0;

            $tenant->run(function () use (&$updated) {
                $jc = app(JsonCargoService::class);

                Container::whereNotNull('carrier_scac')
                    ->whereNotIn('status', ['EMPTY_RETURNED', 'DROPPED'])
                    ->get()
                    ->each(function (Container $c) use ($jc, &$updated) {
                        $line = $jc->resolveShippingLine((string) $c->carrier_scac);
                        $data = $jc->getContainerDetails($c->container_number, $line);

                        if (! $data || isset($data['error'])) {
                            return; // leave as-is; prefix unmapped or no data
                        }

                        $status = $this->mapStatus($data['container_status'] ?? null);
                        $eta    = $data['eta_final_destination'] ?? ($data['eta_next_destination'] ?? null);
                        $atd    = $data['atd_origin'] ?? null;

                        $meta = $c->metadata ?? [];
                        $meta['jsoncargo'] = [
                            'status_text'    => $data['container_status'] ?? null,
                            'last_location'  => $data['last_location'] ?? null,
                            'current_vessel' => $data['current_vessel_name'] ?? null,
                            'current_voyage' => $data['current_voyage_number'] ?? null,
                            'last_updated'   => $data['last_updated'] ?? null,
                            'synced_at'      => Carbon::now()->toIso8601String(),
                        ];

                        $upd = ['metadata' => $meta];
                        if ($status) $upd['status'] = $status;
                        if ($eta) { try { $upd['eta'] = Carbon::parse($eta); } catch (\Throwable $e) {} }
                        if ($atd) { try { $upd['atd'] = Carbon::parse($atd); } catch (\Throwable $e) {} }

                        $c->update($upd);
                        $updated++;
                    });
            });

            $this->info("Tenant {$tenant->id}: {$updated} containers refreshed.");
            $grandTotal += $updated;
        }

        $this->info("Done. {$grandTotal} containers refreshed across {$tenants->count()} tenant(s).");

        return self::SUCCESS;
    }

    private function mapStatus(?string $s): ?string
    {
        $s = strtolower($s ?? '');
        if ($s === '') return null;
        if (str_contains($s, 'discharged') || str_contains($s, 'discharge from')) return 'AT_OCEAN_TERMINAL';
        if (str_contains($s, 'arrival') || str_contains($s, 'arrived'))            return 'AWAITING_DISCHARGE';
        if (str_contains($s, 'departure') || str_contains($s, 'departed')
            || str_contains($s, 'transshipment') || str_contains($s, 'water')
            || str_contains($s, 'sailing'))                                        return 'ON_WATER';
        if (str_contains($s, 'loaded') || str_contains($s, 'load on'))             return 'LOADED_ON_VESSEL';
        if (str_contains($s, 'out for delivery') || str_contains($s, 'delivered')) return 'OUT_FOR_DELIVERY';
        if (str_contains($s, 'empty return'))                                      return 'EMPTY_RETURNED';
        if (str_contains($s, 'gate in') || str_contains($s, 'received')
            || str_contains($s, 'empty'))                                          return 'AT_ORIGIN';
        return 'ON_WATER';
    }
}
