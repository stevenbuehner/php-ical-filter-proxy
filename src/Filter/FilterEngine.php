<?php

declare(strict_types=1);

namespace App\Filter;

use App\Calendar\CalendarEvent;
use App\Config\Dto\FilterRuleConfig;

final readonly class FilterEngine
{
    public function __construct(
        private MatchEvaluator $matchEvaluator,
        private TransformEngine $transformEngine,
    ) {
    }

    /**
     * @param list<CalendarEvent> $events
     * @param list<FilterRuleConfig> $rules
     */
    public function apply(array $events, array $rules): FilterEngineResult
    {
        $currentEvents = $events;
        $perRuleRemovedCount = [];
        $perRuleKeptCount = [];
        $warnings = [];

        foreach ($rules as $index => $rule) {
            $ruleName = $this->ruleName($rule, $index);
            $before = count($currentEvents);

            if ($rule->match === []) {
                $warnings[] = sprintf("Rule '%s' has empty match config and was skipped.", $ruleName);
                $perRuleRemovedCount[$ruleName] = 0;
                $perRuleKeptCount[$ruleName] = $before;
                continue;
            }

            $action = $this->normalizeAction($rule->action);
            $nextEvents = [];
            $removed = 0;

            foreach ($currentEvents as $event) {
                $isMatch = $this->matchEvaluator->matches($event, $rule->match);

                if ($action === 'remove') {
                    if ($isMatch) {
                        $removed++;
                        continue;
                    }

                    $nextEvents[] = $event;
                    continue;
                }

                if ($isMatch) {
                    $this->transformEngine->apply($event, $rule);
                    $nextEvents[] = CalendarEvent::fromVEvent($event->originalEvent);
                    continue;
                }

                $removed++;
            }

            $currentEvents = $nextEvents;
            $perRuleRemovedCount[$ruleName] = $removed;
            $perRuleKeptCount[$ruleName] = count($currentEvents);
        }

        return new FilterEngineResult(
            filteredEvents: $currentEvents,
            statistics: [
                'events_before' => count($events),
                'events_after' => count($currentEvents),
                'per_rule_removed_count' => $perRuleRemovedCount,
                'per_rule_kept_count' => $perRuleKeptCount,
                'warnings' => $warnings,
            ],
        );
    }

    private function normalizeAction(string $action): string
    {
        $normalized = strtolower(trim($action));

        return $normalized === 'keep' ? 'keep' : 'remove';
    }

    private function ruleName(FilterRuleConfig $rule, int $index): string
    {
        $name = trim($rule->name);

        return $name !== '' ? $name : sprintf('rule_%d', $index + 1);
    }
}
