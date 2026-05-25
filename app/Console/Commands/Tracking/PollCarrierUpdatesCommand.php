<?php

namespace App\Console\Commands\Tracking;

use App\Jobs\Tracking\PollCarrierJob;
use App\Models\Tenant\TrackingRequest;
use Illuminate\Console\Command;

class PollCarrierUpdatesCommand extends Command
{
    protected $signature = 'tracking:poll-carriers
                            {--limit=100 : Maximum number of tracking requests to dispatch}';

    protected $description = 'Dispatch PollCarrierJob for all active tracking requests';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        $requests = TrackingRequest::whereIn('status', ['pending', 'active'])
            ->whereNull('last_polled_at')
            ->orWhere(function ($q) {
                $q->whereIn('status', ['pending', 'active'])
                  ->where('last_polled_at', '<', now()->subMinutes(30));
            })
            ->limit($limit)
            ->get();

        if ($requests->isEmpty()) {
            $this->info('No active tracking requests to poll.');
            return self::SUCCESS;
        }

        $dispatched = 0;

        foreach ($requests as $request) {
            PollCarrierJob::dispatch($request);
            $dispatched++;
        }

        $this->info("Dispatched {$dispatched} PollCarrierJob(s).");

        return self::SUCCESS;
    }
}
