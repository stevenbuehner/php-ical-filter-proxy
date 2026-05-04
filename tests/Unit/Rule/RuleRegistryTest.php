<?php

declare(strict_types=1);

namespace Tests\Unit\Rule;

use App\Calendar\CalendarEvent;
use App\Rule\Contract\FilterTypeInterface;
use App\Rule\Contract\TransformTypeInterface;
use App\Rule\RuleRegistry;
use PHPUnit\Framework\TestCase;

final class RuleRegistryTest extends TestCase
{
    public function testResolvesRegisteredFilterAndTransformTypes(): void
    {
        $filter = new class () implements FilterTypeInterface {
            public function type(): string
            {
                return 'match';
            }

            public function validate(array $parameters): array
            {
                return [];
            }

            public function matches(CalendarEvent $event, array $parameters): bool
            {
                return true;
            }
        };

        $transform = new class () implements TransformTypeInterface {
            public function type(): string
            {
                return 'prefix_text';
            }

            public function validate(array $parameters): array
            {
                return [];
            }

            public function apply(CalendarEvent $event, array $parameters): void
            {
            }
        };

        $registry = new RuleRegistry(
            filters: ['match' => $filter],
            transformations: ['prefix_text' => $transform],
        );

        self::assertSame($filter, $registry->filter('match'));
        self::assertSame($transform, $registry->transform('prefix_text'));
    }

    public function testUnknownTypeThrows(): void
    {
        $registry = new RuleRegistry([], []);

        $this->expectException(\InvalidArgumentException::class);
        $registry->filter('missing');
    }

    public function testUnknownTransformTypeThrows(): void
    {
        $registry = new RuleRegistry([], []);

        $this->expectException(\InvalidArgumentException::class);
        $registry->transform('missing');
    }
}
