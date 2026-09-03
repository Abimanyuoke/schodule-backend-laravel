<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use Illuminate\Console\Command;

class PurgeExpiredSchedules extends Command
{
    protected $signature = 'schedules:purge-expired';

    protected $description = 'Delete schedules whose scheduled date has passed';

    public function handle(): int
    {
        $deleted = Schedule::query()
            ->whereNotNull('schedule_date')
            ->whereDate('schedule_date', '<', today())
            ->delete();

        $this->info("Deleted {$deleted} expired schedule(s).");

        return self::SUCCESS;
    }
}