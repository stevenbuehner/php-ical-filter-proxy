<?php

declare(strict_types=1);

namespace App\Filter;

use App\Calendar\CalendarEvent;
use App\Config\Dto\FilterRuleConfig;

final readonly class FilterEngine
{
    /**
     * Wendet die definierte Filterliste sequenziell auf alle Events an.
     *
     * Verarbeitungslogik:
     * - Regeln werden exakt in YAML-Reihenfolge ausgeführt.
     * - Jede Regel sieht immer den aktuellen Stand des Events.
     * - `onMatch=remove` entfernt das Event.
     * - `onMatch=transform` mutiert das Event über die TransformEngine.
     * - Nicht passende Events bleiben unverändert im Ergebnis.
     *
     * Diese Engine ist bewusst einfach gehalten: Sie orchestriert nur das
     * Abhängigkeitsverhältnis zwischen MatchEvaluator, TransformEngine und der
     * Reihenfolge der Regeln. Fachlogik liegt in den jeweiligen Helfern.
     *
     * @param list<CalendarEvent> $events
     * @param list<FilterRuleConfig> $rules
     */
    public function __construct(
        private MatchEvaluator $matchEvaluator = new MatchEvaluator(),
        private TransformEngine $transformEngine = new TransformEngine(),
    ) {
    }

    /**
     * Verarbeitet eine Event-Liste mit einer Liste von Regeln.
     *
     * Das Ergebnis enthält:
     * - die gefilterten Events
     * - Statistiken pro Regel
     * - Warnungen, z. B. wenn eine Match-Regel ohne Bedingungen konfiguriert wurde
     *
     * Wichtig: Jede Regel sieht die Ausgabe der vorherigen Regel. Dadurch können
     * mehrere Filter und Transformationen nacheinander auf denselben Eventsatz wirken.
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
                // Erst prüfen wir, ob die Regel auf dieses Event überhaupt zutrifft.
                $matches = $this->matches($event, $rule);

                if (!$matches) {
                    $nextEvents[] = $event;
                    continue;
                }

                // Ein Treffer mit "remove" beendet die Verarbeitung dieses Events
                // für die aktuelle Regel, das Event wird nicht übernommen.
                if ($rule->onMatch === 'remove') {
                    $removed++;
                    continue;
                }

                // Ein Treffer mit "transform" mutiert das Original-VEVENT.
                if ($rule->onMatch === 'transform') {
                    $this->transformEngine->apply($event, $rule);
                }

                // Nach Remove/Transform wird immer ein frischer CalendarEvent
                // aus dem aktuell mutierten VEvent erzeugt, damit Folge-Regeln
                // mit dem neuesten Stand arbeiten.
                $nextEvents[] = CalendarEvent::fromVEvent($event->originalEvent);
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
        // Aktuell ist "match" der zentrale Filtertyp. Die Typprüfung bleibt
        // bewusst hier, damit spätere zusätzliche Filtertypen sauber ergänzt
        // werden können, ohne die Sequenzlogik zu verändern.
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
