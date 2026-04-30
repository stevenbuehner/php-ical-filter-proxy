<?php

declare(strict_types=1);

namespace App\Filter;

use App\Calendar\CalendarEvent;

final readonly class FilterEngineResult
{
    /**
     * @param list<CalendarEvent> $filteredEvents
     * @param array<string, mixed> $statistics
     */
    public function __construct(
        public array $filteredEvents,
        public array $statistics,
    ) {
    }
}
