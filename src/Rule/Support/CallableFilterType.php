<?php

declare(strict_types=1);

namespace App\Rule\Support;

use App\Calendar\CalendarEvent;
use App\Config\ValidationError;
use App\Rule\Contract\FilterTypeInterface;
use Closure;

final readonly class CallableFilterType implements FilterTypeInterface
{
    /**
     * @param callable(array<string, mixed>): list<ValidationError> $validator
     * @param callable(CalendarEvent, array<string, mixed>): bool $matcher
     */
    public function __construct(
        private string $type,
        private Closure $validator,
        private Closure $matcher,
    ) {
    }

    public function type(): string
    {
        return $this->type;
    }

    public function validate(array $parameters): array
    {
        return ($this->validator)($parameters);
    }

    public function matches(CalendarEvent $event, array $parameters): bool
    {
        return ($this->matcher)($event, $parameters);
    }
}
