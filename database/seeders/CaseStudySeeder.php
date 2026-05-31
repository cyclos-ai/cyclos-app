<?php

namespace Database\Seeders;

use App\Models\Central\Tenant;
use App\Models\Tenant\Container;
use App\Models\Tenant\DrayageCarrier;
use App\Models\Tenant\ImportDrayage;
use App\Models\Tenant\MBL;
use App\Models\Tenant\Organization;
use App\Models\Tenant\Vessel;
use Illuminate\Database\Seeder;

/**
 * Seeds a full case study: 10 ZIM containers from Vietnam → Port Everglades → Baers Furniture
 *
 * Chain: Organization → Vessel → MBL → 10 Containers → Drayage Carrier → 10 Import Drayage orders
 *
 * Run: php artisan tenants:run "db:seed --class=CaseStudySeeder"
 * Or:  php artisan tinker, then tenancy()->initialize(Tenant::find('demo')); then Artisan::call('db:seed', ['--class' => 'CaseStudySeeder']);
 */
class CaseStudySeeder extends Seeder
{
    public function run(): void
    {
        // ── Ensure we're in tenant context ──
        $tenant = Tenant::first();

        if (! $tenant) {
            $this->command?->error('No tenant found. Create a tenant first.');
            return;
        }

        if (! tenancy()->initialized) {
            tenancy()->initialize($tenant);
        }

        $this->command?->info('Seeding case study for tenant: ' . $tenant->id);

        // ================================================================
        // 1. Organization — EFL Container Lines (the shipper/NVOCC)
        // ================================================================
        $org = Organization::firstOrCreate(
            ['name' => 'EFL Container Lines'],
            [
                'display_name' => 'EFL Container Lines (NVOCC)',
                'industry'     => 'Freight & Logistics',
                'city'         => 'Fort Lauderdale',
                'state'        => 'FL',
                'country'      => 'US',
                'phone'        => '(954) 555-0100',
            ]
        );

        $this->command?->info("  Organization: {$org->name} [{$org->id}]");

        // ================================================================
        // 2. Drayage Carrier — Freight Hub Group
        // ================================================================
        $carrier = DrayageCarrier::firstOrCreate(
            ['organization_id' => $org->id, 'scac' => 'FHGP'],
            [
                'company_name'   => 'Freight Hub Group',
                'usdot'          => '3456789',
                'mc_number'      => 'MC-987654',
                'contact_name'   => 'Luis Lopez',
                'contact_email'  => 'dispatch@freighthubgroup.com',
                'contact_phone'  => '(954) 555-0200',
                'address'        => '1234 NW 21st Terrace',
                'city'           => 'Fort Lauderdale',
                'state'          => 'FL',
                'zip'            => '33311',
                'status'         => 'active',
                'fleet_size'     => 25,
                'equipment_types' => ['container', 'flatbed', 'dryvan'],
                'service_areas'  => ['FL', 'GA', 'SC', 'NC'],
            ]
        );

        $this->command?->info("  Drayage Carrier: {$carrier->company_name} (SCAC: {$carrier->scac})");

        // ================================================================
        // 3. Vessel — MSC RIKKU (ZIM uses MSC vessels on transshipment)
        // ================================================================
        $vessel = Vessel::firstOrCreate(
            ['organization_id' => $org->id, 'name' => 'MSC RIKKU'],
            [
                'imo_number'    => '9839284',
                'mmsi'          => '255806522',
                'call_sign'     => '9HA5688',
                'flag'          => 'MT', // Malta
                'vessel_type'   => 'Container Ship',
                'voyage_number' => 'ZI2506E',
                'carrier_scac'  => 'ZIMU',
                'pol'           => 'VNHPH',  // Hai Phong, Vietnam
                'pod'           => 'USEVG',  // Port Everglades, FL
                'etd'           => now()->subDays(28),
                'eta'           => now()->addDays(2),
                'atd'           => now()->subDays(27),
                'metadata'      => [
                    'teu_capacity' => 16652,
                    'gross_tonnage' => 157092,
                    'year_built' => 2022,
                    'length' => 365.9,
                    'breadth' => 51.0,
                ],
            ]
        );

        $this->command?->info("  Vessel: {$vessel->name} (IMO: {$vessel->imo_number})");

        // ================================================================
        // 4. MBL — Master Bill of Lading for ZIM shipment
        // ================================================================
        $mbl = MBL::firstOrCreate(
            ['organization_id' => $org->id, 'mbl_number' => 'ZIMUEVG250610'],
            [
                'carrier_scac'    => 'ZIMU',
                'vessel_id'       => $vessel->id,
                'pol'             => 'VNHPH',  // Hai Phong, Vietnam
                'pod'             => 'USEVG',  // Port Everglades, FL
                'etd'             => now()->subDays(28),
                'eta'             => now()->addDays(2),
                'atd'             => now()->subDays(27),
                'container_count' => 10,
                'status'          => 'active',
                'shipper_name'    => 'Vietnam Furniture Manufacturing Co., Ltd.',
                'consignee_name'  => 'EFL Container Lines',
                'notify_party'    => 'Baers Furniture',
            ]
        );

        $this->command?->info("  MBL: {$mbl->mbl_number} (carrier: ZIMU)");

        // ================================================================
        // 5. 10 Containers — all 40HC dry from Vietnam
        // ================================================================
        $containerNumbers = [
            'ZCSU7244544',
            'CAAU8068182',
            'JXLU4329740',
            'ZCSU7706220',
            'JXLU6442186',
            'TLLU4593024',
            'JXLU6463928',
            'ZCSU7350443',
            'ZCSU7942890',
            'ZCSU7664146',
        ];

        // Assign different statuses to simulate a realistic shipment in progress
        $statuses = [
            'ON_WATER',            // ZCSU7244544 — en route
            'ON_WATER',            // CAAU8068182 — en route
            'ON_WATER',            // JXLU4329740 — en route
            'ON_WATER',            // ZCSU7706220 — en route
            'ON_WATER',            // JXLU6442186 — en route
            'ON_WATER',            // TLLU4593024 — en route
            'ON_WATER',            // JXLU6463928 — en route
            'ON_WATER',            // ZCSU7350443 — en route
            'ON_WATER',            // ZCSU7942890 — en route
            'ON_WATER',            // ZCSU7664146 — en route
        ];

        // Weights vary per container (furniture shipments: 18-24 metric tons)
        $weights = [22500, 19800, 21300, 23100, 20400, 18900, 22800, 21700, 19500, 23400];

        $containers = [];

        foreach ($containerNumbers as $i => $number) {
            $container = Container::firstOrCreate(
                ['organization_id' => $org->id, 'container_number' => $number],
                [
                    'mbl_id'            => $mbl->id,
                    'vessel_id'         => $vessel->id,
                    'status'            => $statuses[$i],
                    'size'              => '40',
                    'type'              => 'DRY',
                    'weight'            => $weights[$i],
                    'weight_unit'       => 'KG',
                    'carrier_scac'      => 'ZIMU',
                    'shipping_line'     => 'ZIM Integrated Shipping',
                    'pol'               => 'VNHPH',
                    'pod'               => 'USEVG',
                    'final_destination' => 'Baers Furniture, Pompano Beach, FL',
                    'etd'               => now()->subDays(28),
                    'atd'               => now()->subDays(27),
                    'eta'               => now()->addDays(2),
                    'is_priority'       => $i < 3, // First 3 are priority
                    'priority_note'     => $i < 3 ? 'Showroom inventory - urgent' : null,
                    'notes'             => "FCL furniture import from Vietnam. Consignee: Baers Furniture.",
                    'metadata'          => [
                        'commodity'        => 'Furniture',
                        'hs_code'         => '9403.60',
                        'origin_factory'  => 'Vietnam Furniture Manufacturing Co., Ltd.',
                        'origin_city'     => 'Hai Phong',
                        'origin_country'  => 'VN',
                        'incoterm'        => 'FOB',
                        'nvocc'           => 'EFL Container Lines',
                        'ocean_carrier'   => 'ZIM',
                        'seal'            => 'ZIM' . str_pad($i + 1, 6, '0', STR_PAD_LEFT),
                    ],
                ]
            );

            $containers[] = $container;
        }

        $this->command?->info("  Containers: " . count($containers) . " created (40HC DRY, ZIMU)");

        // ================================================================
        // 6. Import Drayage orders — one per container
        //    Pickup: Port Everglades Terminal → Delivery: Baers Furniture
        // ================================================================
        $drayageStatuses = [
            'pending',     // ZCSU7244544 — not yet assigned
            'pending',     // CAAU8068182
            'tendered',    // JXLU4329740 — sent to carrier
            'confirmed',   // ZCSU7706220 — carrier accepted
            'confirmed',   // JXLU6442186
            'pending',     // TLLU4593024
            'pending',     // JXLU6463928
            'tendered',    // ZCSU7350443
            'pending',     // ZCSU7942890
            'pending',     // ZCSU7664146
        ];

        foreach ($containers as $i => $container) {
            ImportDrayage::firstOrCreate(
                ['organization_id' => $org->id, 'container_id' => $container->id],
                [
                    'drayage_type'          => 'full',
                    'load_type'             => 'drop',
                    'drayage_status'        => $drayageStatuses[$i],
                    'status'                => $drayageStatuses[$i],
                    'drayage_provider_scac' => 'FHGP',
                    'drayage_provider_name' => 'Freight Hub Group',
                    'drayage_leg'           => 1,
                    'pickup_location'       => 'Port Everglades Terminal, Fort Lauderdale, FL',
                    'delivery_location'     => 'Baers Furniture, 1515 N Federal Hwy, Pompano Beach, FL 33062',
                    'appointment_date'      => now()->addDays(3 + $i), // stagger appointments
                    'notes'                 => "Container {$container->container_number} - 40HC furniture from Vietnam. Drop & pick at Baers warehouse.",
                    'metadata'              => [
                        'pickup_terminal_code' => 'USEVG',
                        'delivery_type'        => 'warehouse',
                        'requires_liftgate'    => false,
                        'hazmat'               => false,
                        'overweight'           => $container->weight > 22000,
                    ],
                ]
            );
        }

        $this->command?->info("  Import Drayage: 10 orders (FHGP → Baers Furniture)");

        // ================================================================
        // Summary
        // ================================================================
        $this->command?->newLine();
        $this->command?->info('Case study seeded successfully!');
        $this->command?->info('  Shipper:  EFL Container Lines (NVOCC)');
        $this->command?->info('  Carrier:  Freight Hub Group (SCAC: FHGP)');
        $this->command?->info('  Ocean:    ZIM (ZIMU) on MSC RIKKU');
        $this->command?->info('  Route:    Hai Phong, VN → Port Everglades, FL');
        $this->command?->info('  Delivery: Baers Furniture, Pompano Beach, FL');
        $this->command?->info('  Containers: 10x 40HC DRY (furniture)');
        $this->command?->info('  MBL:      ZIMUEVG250610');
    }
}
