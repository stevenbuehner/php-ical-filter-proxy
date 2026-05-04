<?php

declare(strict_types=1);

namespace App\Rule;

use App\Calendar\CalendarEvent;
use App\Config\Dto\FilterRuleConfig;
use App\Filter\MatchEvaluator;
use App\Filter\TransformEngine;
use App\Rule\Contract\FilterTypeInterface;
use App\Rule\Contract\TransformTypeInterface;
use App\Rule\Support\CallableFilterType;
use App\Rule\Support\CallableTransformType;

final readonly class RuleRegistry
{
    /**
     * @param array<string, FilterTypeInterface> $filters
     * @param array<string, TransformTypeInterface> $transformations
     */
    public function __construct(
        private array $filters,
        private array $transformations,
    ) {
    }

    public static function default(): self
    {
        $matchEvaluator = new MatchEvaluator();
        $transformEngine = new TransformEngine();

        $filters = [
            'match' => new CallableFilterType(
                'match',
                fn (array $parameters): array => [],
                fn (CalendarEvent $event, array $parameters): bool => $matchEvaluator->matches($event, $parameters)
            ),
        ];

        $transformations = [];
        foreach (['prefix_text', 'suffix_text', 'replace_text', 'replace_regex', 'remove_property', 'categories_add', 'categories_remove', 'adjust_times', 'modify_datetime'] as $type) {
            $transformations[$type] = new CallableTransformType(
                $type,
                fn (array $parameters): array => [],
                function (CalendarEvent $event, array $parameters) use ($transformEngine, $type): void {
                    $transformEngine->apply($event, new FilterRuleConfig(
                        type: 'match',
                        match: ['any' => true],
                        onMatch: 'transform',
                        transform: [array_merge(['type' => $type], $parameters)],
                    ));
                }
            );
        }

        return new self($filters, $transformations);
    }

    public function filter(string $type): FilterTypeInterface
    {
        if (!isset($this->filters[$type])) {
            throw new \InvalidArgumentException(sprintf('Unknown filter type: %s', $type));
        }

        return $this->filters[$type];
    }

    public function transform(string $type): TransformTypeInterface
    {
        if (!isset($this->transformations[$type])) {
            throw new \InvalidArgumentException(sprintf('Unknown transform type: %s', $type));
        }

        return $this->transformations[$type];
    }

    /** @return list<string> */
    public function filterTypes(): array
    {
        return array_keys($this->filters);
    }

    /** @return list<string> */
    public function transformTypes(): array
    {
        return array_keys($this->transformations);
    }
}
