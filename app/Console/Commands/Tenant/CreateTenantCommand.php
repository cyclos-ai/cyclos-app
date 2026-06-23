<?php

namespace App\Console\Commands\Tenant;

use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use App\Models\Tenant\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create
                            {name : The tenant/organization name}
                            {email : Admin user login email address}
                            {--slug= : Custom slug/subdomain (auto-generated from name if omitted)}
                            {--first= : Admin first name}
                            {--last= : Admin last name}
                            {--plan= : Subscription plan identifier}';

    protected $description = 'Create a new tenant with domain, database, and a loginable admin user';

    public function handle(): int
    {
        $name  = $this->argument('name');
        $email = $this->argument('email');
        $slug  = $this->option('slug') ?: Str::slug($name);
        $plan  = $this->option('plan') ?: 'starter';
        $first = $this->option('first') ?: 'Admin';
        $last  = $this->option('last') ?: $name;

        if (Tenant::where('id', $slug)->orWhere('slug', $slug)->exists()) {
            $this->error("A tenant with slug '{$slug}' already exists.");
            return self::FAILURE;
        }

        $this->info("Creating tenant: {$name} (slug: {$slug})");

        try {
            $password = Str::random(16);

            DB::transaction(function () use ($name, $email, $slug, $plan, $first, $last, $password) {
                // Tenant. NOTE: slug + email are NOT NULL columns; the lifecycle
                // pipeline (CreateDatabase -> Migrate -> Seed) runs synchronously and
                // requires TenancyServiceProvider's JobPipeline ->toListener().
                /** @var Tenant $tenant */
                $tenant = Tenant::create([
                    'id'    => $slug,
                    'slug'  => $slug,
                    'name'  => $name,
                    'email' => $email,
                    'plan'  => $plan,
                ]);

                // Subdomain identification matches the LABEL (e.g. "baers"), not the
                // FQDN — stancl InitializeTenancyBySubdomain strips the central domain.
                Domain::firstOrCreate(['domain' => $slug], ['tenant_id' => $tenant->id]);

                // Loginable user lives in the CENTRAL users table (the api guard +
                // AuthController::login authenticate there, not in the tenant DB).
                User::create([
                    'first_name'      => $first,
                    'last_name'       => $last,
                    'email'           => $email,
                    'password'        => $password, // 'hashed' cast hashes on set
                    'role'            => 'shipper_admin',
                    'is_active'       => true,
                    'approval_status' => 'approved',
                    'tenant_id'       => $tenant->id,
                    'company_name'    => $name,
                ]);

                // Tenant data models FK organization_id -> organizations.id, and the
                // controllers use the tenant id as organization_id. So the org row's
                // id MUST equal the tenant id.
                $tenant->run(function () use ($name, $tenant) {
                    Organization::firstOrCreate(['id' => $tenant->id], ['name' => $name]);
                });
            });

            $this->info("Tenant '{$name}' created successfully.");
            $this->line("  Login URL:     https://{$slug}.cyclos.ai");
            $this->line("  Login email:   {$email}");
            $this->line("  Temp password: {$password}");
            $this->warn("  Share this password securely and prompt the user to change it on first login.");
            $this->warn("  Also add a Caddy block for {$slug}.cyclos.ai and force-recreate the caddy container.");

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("Failed to create tenant: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
