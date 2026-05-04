<?php

declare(strict_types=1);

namespace App\Rule\Support;

use App\Calendar\CalendarEvent;
use App\Config\ValidationError;
use App\Rule\Contract\TransformTypeInterface;

final readonly class CallableTransformType implements TransformTypeInterface
{
    /**
     * @param callable(array<string, mixed>): list<ValidationError> $validator
     * @param callable(CalendarEvent, array<string, mixed>): void $applier
     */
    public function __construct(
        private string $type,
        private $validator,
        private $applier,
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

    public function apply(CalendarEvent $event, array $parameters): void
    {
        ($this->applier)($event, $parameters);
    }
}
