<?php

declare(strict_types=1);

namespace App\Calendar;

use App\Config\Dto\EventMigrationConfig;
use App\Config\Dto\ExportConfig;

interface EventMigrationStrategyInterface
{
    public function name(): string;

    /**
     * @param list<CalendarEvent> $events
     */
    public function merge(array $events, ExportConfig $export, EventMigrationConfig $config): CalendarEvent;
}
