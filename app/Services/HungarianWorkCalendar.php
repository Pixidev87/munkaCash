<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Config;

class HungarianWorkCalendar
{
    public const string TIMEZONE = 'Europe/Budapest';

    public function isWorkday(CarbonImmutable $date): ?bool
    {
        $localDate = $date->setTimezone(self::TIMEZONE);
        $calendar = Config::get('reminder-calendar.'.$localDate->year);

        if (! is_array($calendar)) {
            return null;
        }

        $day = $localDate->format('m-d');

        return ! in_array($day, $calendar['days_off'], true)
            && ($localDate->isWeekday() || in_array($day, $calendar['workdays'], true));
    }

    public function effectiveDeadline(CarbonInterface $invoiceDate): ?CarbonImmutable
    {
        // The stored date is a calendar day, rather than an instant to convert.
        $date = CarbonImmutable::parse($invoiceDate->toDateString(), self::TIMEZONE);

        while (($workday = $this->isWorkday($date)) === false) {
            $date = $date->addDay();
        }

        return $workday === true ? $date : null;
    }
}
