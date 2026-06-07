<?php

namespace App\Console\Commands\Billing;

use App\Models\Central\Tenant;
use App\Services\Billing\UsageMeteringService;
use App\Services\Stripe\StripeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReportUsageToStripe extends Command
{
    protected $signature = 'billing:report-usage';

    protected $description = 'Report unreported billable usage to Stripe Billing Meters';

    public function handle(UsageMeteringService $metering): int
    {
        $stripe = app(StripeService::class);

        $tenants = Tenant::query()
            ->whereNotNull('stripe_customer_id')
            ->whereHas('subscription', function ($query) {
                $query->where('status', 'active');
            })
            ->get();

        $this->info("Reporting usage for {$tenants->count()} tenant(s)...");

        foreach ($tenants as $tenant) {
            try {
                $rows = $metering->unreportedBillable($tenant->id);

                if ($rows->isEmpty()) {
                    continue;
                }

                $tokenTotal = (int) $rows
                    ->where('metric', UsageMeteringService::METRIC_AI_TOKENS)
                    ->sum('quantity');

                $callTotal = (int) $rows
                    ->where('metric', UsageMeteringService::METRIC_API_CALLS_EXTERNAL)
                    ->sum('quantity');

                if ($tokenTotal > 0) {
                    $stripe->reportMeterEvent($tenant->stripe_customer_id, 'cyclos_ai_tokens', $tokenTotal);
                }

                if ($callTotal > 0) {
                    $stripe->reportMeterEvent($tenant->stripe_customer_id, 'cyclos_api_calls', $callTotal);
                }

                $metering->markReported($rows->pluck('id')->all());

                $this->line("  Reported tenant {$tenant->id}: {$tokenTotal} tokens, {$callTotal} calls");
            } catch (\Throwable $e) {
                Log::error('ReportUsageToStripe: failed for tenant', [
                    'tenant_id' => $tenant->id,
                    'error'     => $e->getMessage(),
                ]);

                $this->error("  Failed for tenant {$tenant->id}: {$e->getMessage()}");
            }
        }

        $this->info('Usage reporting complete.');

        return self::SUCCESS;
    }
}
