<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class ClosedPilotHeartbeat
{
    public const string SCHEDULER = 'closed-pilot:scheduler';

    public const string WORKER = 'closed-pilot:worker';

    public function mark(string $process): void
    {
        Cache::put($process, true, now()->addMinutes(3));
    }
}
