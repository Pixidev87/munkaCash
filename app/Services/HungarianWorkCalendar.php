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

    /**
     * @return list<array{string, string}> Inclusive start and exclusive end.
     */
    public function overdueRanges(CarbonImmutable $today): array
    {
        $yesterday = $today->setTimezone(self::TIMEZONE)->startOfDay()->subDay();
        $ranges = [];

        foreach (Config::array('reminder-calendar') as $year => $calendar) {
            if (! is_int($year) || $year > $yesterday->year || ! is_array($calendar)) {
                continue;
            }

            $start = $yesterday->setDate($year, 1, 1);
            $end = $start->setDate($year, 12, 31)->min($yesterday);
            $deadline = $this->effectiveDeadline($end);

            if ($deadline === null || $deadline->gt($yesterday)) {
                // The trailing non-workdays are still pending or lack a verified next workday.
                while ($end->gte($start) && $this->isWorkday($end) !== true) {
                    $end = $end->subDay();
                }
            }

            if ($end->gte($start)) {
                $ranges[] = [$start->toDateString(), $end->addDay()->toDateString()];
            }
        }

        return $ranges;
    }
}
