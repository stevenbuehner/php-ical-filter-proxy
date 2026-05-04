<?php

declare(strict_types=1);

namespace Tests\Unit\Filter\Match;

use App\Filter\Match\DateMatchEvaluator;
use PHPUnit\Framework\TestCase;

final class DateMatchEvaluatorTest extends TestCase
{
    public function testDateRangeMatchingWorks(): void
    {
        $evaluator = new DateMatchEvaluator();

        self::assertTrue($evaluator->matches('2026-05-01 12:00:00', ['from' => '2026-01-01', 'until' => '2026-12-31']));
        self::assertTrue($evaluator->matches('2026-05-01 12:00:00', ['from' => '-12 months', 'until' => '+12 months']));
        self::assertFalse($evaluator->matches('2026-05-01 12:00:00', ['until' => '2025-12-31']));
    }
}
