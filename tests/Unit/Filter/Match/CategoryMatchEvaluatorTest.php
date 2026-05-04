<?php

declare(strict_types=1);

namespace Tests\Unit\Filter\Match;

use App\Filter\Match\CategoryMatchEvaluator;
use PHPUnit\Framework\TestCase;

final class CategoryMatchEvaluatorTest extends TestCase
{
    public function testCategoryOperators(): void
    {
        $evaluator = new CategoryMatchEvaluator();

        self::assertTrue($evaluator->matches(['Technik', 'Dienst'], 'contains', 'technik'));
        self::assertTrue($evaluator->matches(['Technik', 'Dienst'], 'contains_any', ['foo', 'dienst']));
        self::assertTrue($evaluator->matches(['Technik', 'Dienst'], 'contains_all', ['technik', 'dienst']));
        self::assertTrue($evaluator->matches(['Technik', 'Dienst'], 'equals', ['Dienst', 'Technik']));
        self::assertTrue($evaluator->matches(['Technik', 'Dienst'], 'not_equals', ['Andacht']));
        self::assertTrue($evaluator->matches([], 'empty', true));
    }
}
