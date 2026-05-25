<?php

namespace App\Console\Commands\Tenant;

use App\Models\Central\Tenant;
use Illuminate\Console\Command;

class DeleteTenantCommand extends Command
{
    protected $signature = 'tenant:delete
                            {id : The tenant ID (slug) to delete}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete a tenant and its associated database';

    public function handle(): int
    {
        $id    = $this->argument('id');
        $force = $this->option('force');

        $tenant = Tenant::find($id);

        if ($tenant === null) {
            $this->error("Tenant '{$id}' not found.");
            return self::FAILURE;
        }

        $name = $tenant->name ?? $id;

        if (!$force) {
            $confirmed = $this->confirm(
                "Are you sure you want to permanently delete tenant '{$name}' ({$id})? This cannot be undone.",
                false
            );

            if (!$confirmed) {
                $this->info('Deletion cancelled.');
                return self::SUCCESS;
            }
        }

        $this->info("Deleting tenant: {$name} ({$id})");

        try {
            // Delete all domains first
            $tenant->domains()->delete();
            $this->line('  Domains deleted.');

            // Delete the tenant (stancl/tenancy handles DB deletion via model events)
            $tenant->delete();
            $this->line('  Tenant record deleted.');

            $this->info("Tenant '{$name}' has been deleted successfully.");
            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("Failed to delete tenant: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
