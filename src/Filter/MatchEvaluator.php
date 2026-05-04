<?php

declare(strict_types=1);

namespace App\Filter;

use App\Calendar\CalendarEvent;
use App\Filter\Match\CategoryMatchEvaluator;
use App\Filter\Match\DateMatchEvaluator;
use App\Filter\Match\TextMatchEvaluator;

final readonly class MatchEvaluator
{
    /**
     * Kern-Orchestrator für alle Match-Regeln.
     *
     * Die eigentliche Logik ist absichtlich auf spezialisierte Helfer
     * ausgelagert:
     * - Textfelder: `TextMatchEvaluator`
     * - Kategorien: `CategoryMatchEvaluator`
     * - Datumsbereiche: `DateMatchEvaluator`
     *
     * So bleibt die allgemeine Regelverarbeitung verständlich, während einzelne
     * Match-Mechaniken unabhängig erweitert oder getestet werden können.
     */
    public function __construct(
        private TextMatchEvaluator $textMatchEvaluator = new TextMatchEvaluator(),
        private CategoryMatchEvaluator $categoryMatchEvaluator = new CategoryMatchEvaluator(),
        private DateMatchEvaluator $dateMatchEvaluator = new DateMatchEvaluator(),
    ) {
    }

    /**
     * @param array<string, mixed> $matchConfig
     */
    public function matches(CalendarEvent $event, array $matchConfig): bool
    {
        // Leere Match-Regeln gelten nicht als Treffer. Sie werden in der
        // FilterEngine zusätzlich als Warnung gemeldet.
        if ($matchConfig === []) {
            return false;
        }

        // `any: true` ist der explizite "match everything"-Pfad.
        if (($matchConfig['any'] ?? false) === true) {
            return true;
        }

        foreach ($matchConfig as $field => $conditions) {
            if ((string) $field === 'any') {
                continue;
            }

            if (!is_array($conditions) || $conditions === []) {
                return false;
            }

            if (!$this->matchesField($event, (string) $field, $conditions)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $conditions
     */
    private function matchesField(CalendarEvent $event, string $field, array $conditions): bool
    {
        foreach ($conditions as $operator => $expected) {
            if (!$this->evaluateOperator($event, $field, (string) $operator, $expected)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateOperator(CalendarEvent $event, string $field, string $operator, mixed $expected): bool
    {
        // Jeder Feldtyp hat seine eigene fachliche Auswertung. So bleibt
        // Kategorie- und Datumslogik getrennt von allgemeiner Textsuche.
        return match ($field) {
            'categories' => $this->categoryMatchEvaluator->matches($event->categories, $operator, $expected),
            'date' => $this->matchesDateField($event, $operator, $expected),
            default => $this->textMatchEvaluator->matches($this->fieldAsText($event, $field), $operator, $expected),
        };
    }

    private function matchesDateField(CalendarEvent $event, string $operator, mixed $expected): bool
    {
        if (in_array($operator, ['from', 'until'], true)) {
            return $this->dateMatchEvaluator->matches($event->dtstart, [$operator => $expected]);
        }

        return $this->dateMatchEvaluator->matches($event->dtstart, is_array($expected) ? $expected : []);
    }

    private function fieldAsText(CalendarEvent $event, string $field): string
    {
        // Nicht jeder Feldname ist tatsächlich ein Textfeld im ICS. Unbekannte
        // Felder liefern bewusst einen leeren String, damit sie nicht zufällig
        // matchen.
        return match ($field) {
            'summary' => $event->summary,
            'description' => $event->description,
            'location' => $event->location,
            'url' => $event->url,
            'categories' => implode(',', $event->categories),
            'date' => $event->dtstart ?? '',
            default => '',
        };
    }
}
