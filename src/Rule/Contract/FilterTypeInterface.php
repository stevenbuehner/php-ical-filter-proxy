<?php

declare(strict_types=1);

namespace App\Rule\Contract;

use App\Calendar\CalendarEvent;
use App\Config\ValidationError;

interface FilterTypeInterface
{
    public function type(): string;

    /**
     * @param array<string, mixed> $parameters
     * @return list<ValidationError>
     */
    public function validate(array $parameters): array;

    /**
     * @param array<string, mixed> $parameters
     */
    public function matches(CalendarEvent $event, array $parameters): bool;
}
