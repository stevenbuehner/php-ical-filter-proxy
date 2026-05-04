<?php

declare(strict_types=1);

namespace Tests\Unit\Filter\Match;

use App\Filter\Match\TextMatchEvaluator;
use PHPUnit\Framework\TestCase;

final class TextMatchEvaluatorTest extends TestCase
{
    public function testTextOperatorsAndRegexNormalization(): void
    {
        $evaluator = new TextMatchEvaluator();

        self::assertTrue($evaluator->matches('Technikprobe', 'contains', 'technik'));
        self::assertTrue($evaluator->matches('Technikprobe', 'contains_any', ['foo', 'probe']));
        self::assertTrue($evaluator->matches('Technikprobe', 'contains_all', ['Technik', 'probe']));
        self::assertTrue($evaluator->matches('Technikprobe', 'equals', 'technikprobe'));
        self::assertTrue($evaluator->matches('Technikprobe', 'not_equals', 'Andacht'));
        self::assertTrue($evaluator->matches('Technikprobe', 'regex', '/^Technik/i'));
        self::assertFalse($evaluator->matches('', 'empty', true));
    }
}
