<?php

declare(strict_types=1);

namespace App\Filter;

use App\Calendar\CalendarEvent;
use App\Config\Dto\FilterRuleConfig;

final readonly class FilterEngine
{
    public function __construct(
        private MatchEvaluator $matchEvaluator = new MatchEvaluator(),
        private TransformEngine $transformEngine = new TransformEngine(),
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

            if ($rule->match === [] && $rule->type === 'match') {
                $warnings[] = sprintf("Rule '%s' has empty match config and was skipped.", $ruleName);
                $perRuleRemovedCount[$ruleName] = 0;
                $perRuleKeptCount[$ruleName] = $before;
                continue;
            }

            $nextEvents = [];
            $removed = 0;

            foreach ($currentEvents as $event) {
                $matches = $this->matches($event, $rule);

                if (!$matches) {
                    $nextEvents[] = $event;
                    continue;
                }

                if ($rule->onMatch === 'remove') {
                    $removed++;
                    continue;
                }

                if ($rule->onMatch === 'transform') {
                    $this->transformEngine->apply($event, $rule);
                }

                $nextEvents[] = CalendarEvent::fromVEvent($event->originalEvent);

                if ($rule->stopProcessing) {
                    $nextEvents = array_merge($nextEvents, array_slice($currentEvents, array_search($event, $currentEvents, true) + 1));
                    break;
                }
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

    private function matches(CalendarEvent $event, FilterRuleConfig $rule): bool
    {
        if ($rule->type !== 'match') {
            return false;
        }

        return $this->matchEvaluator->matches($event, $rule->match);
    }

    private function ruleName(FilterRuleConfig $rule, int $index): string
    {
        return sprintf('%s_%d', $rule->type, $index + 1);
    }
}
