<?php

namespace App\Services\Billing;

use App\Models\Central\UsageRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Records per-tenant usage (AI tokens, external API calls, total API calls)
 * into the CENTRAL database, regardless of the current tenant context.
 *
 * Every public mutator is defensive: metering must NEVER break a tenant
 * request. Failures are logged and swallowed.
 */
class UsageMeteringService
{
    public const METRIC_AI_TOKENS         = 'ai_tokens';
    public const METRIC_API_CALLS_EXTERNAL = 'api_calls_external';
    public const METRIC_API_CALLS_TOTAL    = 'api_calls_total';

    /**
     * Record AI token usage (input + output) for today.
     * No-ops when the tenant id is null.
     */
    public function recordTokens(?string $tenantId, int $input, int $output, ?string $model = null): void
    {
        if ($tenantId === null) {
            return;
        }

        $quantity = $input + $output;

        if ($quantity <= 0) {
            return;
        }

        try {
            $this->increment($tenantId, self::METRIC_AI_TOKENS, $quantity);
        } catch (\Throwable $e) {
            Log::warning('UsageMeteringService: recordTokens failed', [
                'tenant_id' => $tenantId,
                'model'     => $model,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record an API call. Always increments the total counter; when billable,
     * also increments the external (billed) counter.
     * No-ops when the tenant id is null.
     */
    public function recordApiCall(?string $tenantId, string $service, bool $billable = true): void
    {
        if ($tenantId === null) {
            return;
        }

        try {
            $this->increment($tenantId, self::METRIC_API_CALLS_TOTAL, 1);

            if ($billable) {
                $this->increment($tenantId, self::METRIC_API_CALLS_EXTERNAL, 1);
            }
        } catch (\Throwable $e) {
            Log::warning('UsageMeteringService: recordApiCall failed', [
                'tenant_id' => $tenantId,
                'service'   => $service,
                'billable'  => $billable,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * Summarise usage for a tenant over a date range (defaults to the current
     * calendar month). Returns a fixed-shape array of integers.
     *
     * @return array{ai_tokens:int,api_calls_external:int,api_calls_total:int}
     */
    public function usageSummary(string $tenantId, ?Carbon $start = null, ?Carbon $end = null): array
    {
        $start = ($start ?? Carbon::now()->startOfMonth())->toDateString();
        $end   = ($end ?? Carbon::now()->endOfMonth())->toDateString();

        $totals = UsageRecord::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('period_date', [$start, $end])
            ->selectRaw('metric, SUM(quantity) AS total')
            ->groupBy('metric')
            ->pluck('total', 'metric');

        return [
            self::METRIC_AI_TOKENS          => (int) ($totals[self::METRIC_AI_TOKENS] ?? 0),
            self::METRIC_API_CALLS_EXTERNAL => (int) ($totals[self::METRIC_API_CALLS_EXTERNAL] ?? 0),
            self::METRIC_API_CALLS_TOTAL    => (int) ($totals[self::METRIC_API_CALLS_TOTAL] ?? 0),
        ];
    }

    /**
     * Billable usage rows (ai_tokens / api_calls_external) not yet reported to
     * the billing provider.
     */
    public function unreportedBillable(string $tenantId): Collection
    {
        return UsageRecord::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('metric', [self::METRIC_AI_TOKENS, self::METRIC_API_CALLS_EXTERNAL])
            ->whereNull('reported_at')
            ->where('quantity', '>', 0)
            ->get();
    }

    /**
     * Mark the given usage record ids as reported (timestamped now).
     */
    public function markReported(array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        UsageRecord::query()
            ->whereIn('id', $ids)
            ->update(['reported_at' => Carbon::now()]);
    }

    /**
     * Atomically ensure today's row for a metric exists, then increment it.
     */
    private function increment(string $tenantId, string $metric, int $amount): void
    {
        $today = Carbon::now()->toDateString();

        $record = UsageRecord::query()->firstOrCreate(
            [
                'tenant_id'   => $tenantId,
                'metric'      => $metric,
                'period_date' => $today,
            ],
            [
                'quantity' => 0,
            ],
        );

        $record->increment('quantity', $amount);
    }
}
