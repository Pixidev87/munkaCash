<?php

namespace Tests\Unit;

use App\Services\HungarianWorkCalendar;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HungarianWorkCalendarTest extends TestCase
{
    #[DataProvider('deadlines')]
    public function test_calculates_the_effective_deadline(string $original, string $expected): void
    {
        $calendar = app(HungarianWorkCalendar::class);
        $date = CarbonImmutable::parse($original, 'Pacific/Auckland');

        $this->assertSame($expected, $calendar->effectiveDeadline($date)?->toDateString());
    }

    /** @return array<string, array{string, string}> */
    public static function deadlines(): array
    {
        return [
            'ordinary workday' => ['2026-12-01', '2026-12-01'],
            'ordinary weekend' => ['2026-12-05', '2026-12-07'],
            'reassigned Saturday' => ['2026-08-08', '2026-08-08'],
            'August national holiday and rest day' => ['2026-08-20', '2026-08-24'],
            'Christmas Eve and Christmas' => ['2026-12-24', '2026-12-28'],
            'December Saturday no longer reassigned' => ['2026-12-12', '2026-12-14'],
        ];
    }

    public function test_unknown_year_returns_null_even_after_a_known_year(): void
    {
        config([
            'reminder-calendar.2026.days_off' => ['12-31'],
            'reminder-calendar.2027' => null,
        ]);
        $calendar = app(HungarianWorkCalendar::class);
        $date = CarbonImmutable::parse('2026-12-31', HungarianWorkCalendar::TIMEZONE);

        $this->assertNull($calendar->effectiveDeadline($date));
        $this->assertNull($calendar->effectiveDeadline($date->addDay()));

        config(['reminder-calendar.2027' => ['days_off' => ['01-01'], 'workdays' => []]]);

        $this->assertSame('2027-01-04', $calendar->effectiveDeadline($date)?->toDateString());
    }
}
