<?php

namespace App\Console\Commands;

use App\Enums\ReminderTestDeliveryStatus as Status;
use App\Models\ReminderTestDelivery;
use App\Services\ClosedPilotHeartbeat;
use App\Services\ReminderTestQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class CheckClosedPilot extends Command
{
    protected $signature = 'munkacash:pilot:check';

    protected $description = 'Check that the closed pilot scheduler and reminder worker are making progress';

    public function handle(): int
    {
        if (! app()->environment('closed-pilot')) {
            $this->error('This check is only available in the closed pilot.');

            return self::FAILURE;
        }

        try {
            $scheduler = Cache::has(ClosedPilotHeartbeat::SCHEDULER);
            $worker = Cache::has(ClosedPilotHeartbeat::WORKER);
            $overdueJobs = DB::table('jobs')->where('queue', ReminderTestQueue::NAME)
                ->where('available_at', '<=', now()->subMinutes(2)->timestamp)->count();
            $unknown = ReminderTestDelivery::query()->where('status', Status::Unknown)
                ->where('updated_at', '>=', now()->subMinutes(15))->count();
        } catch (Throwable) {
            $this->error('Closed pilot database or cache is unavailable.');

            return self::FAILURE;
        }

        $this->table(['Check', 'Result'], [
            ['Scheduler', $scheduler ? 'OK' : 'Heartbeat missing'],
            ['Reminder worker', $worker ? 'OK' : 'Heartbeat missing'],
            ['Queue jobs waiting for 2 minutes or longer', $overdueJobs],
            ['Unconfirmed SMTP attempts updated in the last 15 minutes', $unknown],
        ]);

        return $scheduler && $worker && $overdueJobs === 0 && $unknown === 0
            ? self::SUCCESS
            : self::FAILURE;
    }
}
