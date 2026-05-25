<?php

namespace App\Console\Commands\Tenant;

use App\Models\Central\Domain;
use App\Models\Central\Tenant;
use App\Models\Central\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateTenantCommand extends Command
{
    protected $signature = 'tenant:create
                            {name : The tenant/organization name}
                            {email : Admin user email address}
                            {--slug= : Custom slug (auto-generated from name if omitted)}
                            {--plan= : Subscription plan identifier}';

    protected $description = 'Create a new tenant with domain, database, and initial admin user';

    public function handle(): int
    {
        $name  = $this->argument('name');
        $email = $this->argument('email');
        $slug  = $this->option('slug') ?? Str::slug($name);
        $plan  = $this->option('plan') ?? 'starter';

        if (Tenant::where('id', $slug)->exists()) {
            $this->error("A tenant with slug '{$slug}' already exists.");
            return self::FAILURE;
        }

        $this->info("Creating tenant: {$name} (slug: {$slug})");

        try {
            DB::transaction(function () use ($name, $email, $slug, $plan) {
                /** @var Tenant $tenant */
                $tenant = Tenant::create([
                    'id'   => $slug,
                    'name' => $name,
                    'plan' => $plan,
                ]);

                $domain = $slug . '.' . config('app.domain', 'cyclos.ai');

                Domain::create([
                    'domain'    => $domain,
                    'tenant_id' => $tenant->id,
                ]);

                $this->info("Domain created: {$domain}");

                // Run tenant migrations and seed
                $tenant->run(function () use ($email, $name) {
                    $password = Str::random(16);

                    $user = \App\Models\Tenant\User::create([
                        'name'     => 'Admin',
                        'email'    => $email,
                        'password' => Hash::make($password),
                        'role'     => 'admin',
                    ]);

                    \App\Models\Tenant\Organization::create([
                        'name' => $name,
                    ]);

                    $this->line("  Admin user created: {$email}");
                    $this->line("  Temporary password: {$password}");
                    $this->warn("  Please share this password securely and prompt the user to change it.");
                });
            });

            $this->info("Tenant '{$name}' created successfully.");
            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("Failed to create tenant: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
