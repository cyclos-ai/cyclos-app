<?php

namespace App\Console\Commands\Tracking;

use App\Jobs\Tracking\PollAISPositionsJob;
use Illuminate\Console\Command;

class PollAISPositionsCommand extends Command
{
    protected $signature = 'tracking:poll-ais';

    protected $description = 'Dispatch PollAISPositionsJob to update vessel positions from AIS data';

    public function handle(): int
    {
        PollAISPositionsJob::dispatch();

        $this->info('PollAISPositionsJob dispatched.');

        return self::SUCCESS;
    }
}
