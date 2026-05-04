<?php

declare(strict_types=1);

namespace App\Filter;

use App\Calendar\CalendarEvent;
use App\Config\Dto\FilterRuleConfig;

final class TransformEngine
{
    /**
     * Führt eine Liste von Transformationen auf einem Event aus.
     *
     * Die Transformationen werden in exakt der Reihenfolge aus der YAML-Datei
     * verarbeitet. Dadurch können mehrere Änderungen auf dasselbe Event wirken,
     * z. B. Textpräfix, Kategorieänderung und Zeitverschiebung in einem Schritt.
     *
     * Erwartung an die Eingabe:
     * - die Regel wurde bereits validiert
     * - nur bekannte Transform-Typen werden übergeben
     * - die jeweilige Parameterstruktur passt zum Transform-Typ
     */
    public function apply(CalendarEvent $event, FilterRuleConfig $rule): void
    {
        foreach ($rule->transform as $transform) {
            $type = strtolower(trim((string) ($transform['type'] ?? '')));
            if ($type === '') {
                continue;
            }

            // Jede konkrete Transformation ist bewusst als eigener Pfad
            // implementiert, damit die Semantik pro Typ klar lesbar bleibt.
            $this->applyTransform($event, $type, $transform);
        }
    }

    /**
     * @param array<string, mixed> $transform
     */
    private function applyTransform(CalendarEvent $event, string $type, array $transform): void
    {
        // Zeitbezogene Anpassungen folgen einer eigenen Logik, weil sie nicht
        // nur einen einzelnen Textwert ändern, sondern Start und Ende in
        // Beziehung zueinander setzen.
        if (in_array($type, ['adjust_times'], true)) {
            $this->adjustTimes($event, $transform);
            return;
        }

        // `modify_datetime` ist die einfache Einzel-Feld-Variante für start
        // oder end, ohne Berechnung des Gegenfelds.
        if ($type === 'modify_datetime') {
            $this->modifyDateTime($event, $transform);
            return;
        }

        // Kategorien werden als eigene Property behandelt, nicht als Textfeld.
        if ($type === 'categories_add' || $type === 'categories_remove') {
            $this->transformCategories($event, $type, $transform);
            return;
        }

        // Texttransformationen arbeiten auf klassischen VEVENT-Feldern.
        $field = strtolower(trim((string) ($transform['field'] ?? '')));
        $property = match ($field) {
            'summary' => 'SUMMARY',
            'description' => 'DESCRIPTION',
            'location' => 'LOCATION',
            'url' => 'URL',
            default => null,
        };

        if ($property === null) {
            // remove_property darf auch auf frei benannte Property-Namen wirken.
            if ($type === 'remove_property' && $field !== '') {
                $this->removeProperty($event, $field);
            }
            return;
        }

        $current = isset($event->originalEvent->{$property}) ? (string) $event->originalEvent->{$property} : '';

        // Der konkrete Text-Operator bestimmt die Veränderung.
        $next = match ($type) {
            'prefix_text' => (string) ($transform['value'] ?? '') . $current,
            'suffix_text' => $current . (string) ($transform['value'] ?? ''),
            'replace_text' => str_ireplace((string) ($transform['search'] ?? ''), (string) ($transform['replace'] ?? ''), $current),
            'replace_regex' => $this->replaceRegex($current, (string) ($transform['pattern'] ?? ''), (string) ($transform['replacement'] ?? '')),
            'remove_property' => '',
            default => $current,
        };

        if ($type === 'remove_property') {
            unset($event->originalEvent->{$property});
            return;
        }

        if (!isset($event->originalEvent->{$property})) {
            $event->originalEvent->add($property, $next);
            return;
        }

        $event->originalEvent->{$property} = $next;
    }

    /**
     * Kategorien werden als kommaseparierte ICS-Property geschrieben.
     *
     * Verwendung:
     * - `categories_add`: Wert ergänzen, wenn noch nicht vorhanden
     * - `categories_remove`: Wert fallunabhängig entfernen
     *
     * Wird am Ende keine Kategorie mehr übrig gelassen, wird die Property
     * komplett entfernt.
     *
     * @param array<string, mixed> $transform
     */
    private function transformCategories(CalendarEvent $event, string $type, array $transform): void
    {
        $current = [];
        if (isset($event->originalEvent->CATEGORIES)) {
            $current = array_values(array_filter(array_map('trim', explode(',', (string) $event->originalEvent->CATEGORIES))));
        }

        $value = trim((string) ($transform['value'] ?? ''));
        if ($value !== '') {
            if ($type === 'categories_add' && !in_array($value, $current, true)) {
                $current[] = $value;
            }
            if ($type === 'categories_remove') {
                $current = array_values(array_filter($current, static fn (string $item): bool => strcasecmp($item, $value) !== 0));
            }
        }

        if ($current === []) {
            unset($event->originalEvent->CATEGORIES);
            return;
        }

        $event->originalEvent->CATEGORIES = implode(',', $current);
    }

    /**
     * Verschiebt DTSTART und DTEND gemeinsam.
     *
     * YAML-Form:
     * - `start.reference`: `current_start` oder `current_end`
     * - `start.offset`: z. B. `-20m`, `+10m`, `30s`, `2h`
     * - `end.reference`: dieselben Referenzwerte
     * - `end.offset`: dieselbe Offset-Syntax
     *
     * Regeln:
     * - All-Day-Events werden ignoriert
     * - DTEND darf nie vor DTSTART liegen
     * - falls DURATION vorhanden ist, wird sie neu berechnet
     *
     * @param array<string, mixed> $transform
     */
    private function adjustTimes(CalendarEvent $event, array $transform): void
    {
        // All-Day-Events bleiben unangetastet, weil hier nur DATE-TIME-Logik
        // erwartet wird und keine Tagesgrenzen verschoben werden sollen.
        if ($this->isAllDayEvent($event)) {
            return;
        }

        $currentStart = $this->parseDateTimeProperty($event, 'DTSTART');
        if ($currentStart === null) {
            return;
        }

        $currentEnd = $this->parseDateTimeProperty($event, 'DTEND') ?? $currentStart;
        $startSpec = is_array($transform['start'] ?? null) ? $transform['start'] : [];
        $endSpec = is_array($transform['end'] ?? null) ? $transform['end'] : [];

        // Beide Seiten werden separat berechnet und können unterschiedliche
        // Referenzen verwenden.
        $nextStart = $this->resolveAdjustedTime($currentStart, $currentStart, $currentEnd, $startSpec, 'current_start');
        $nextEnd = $this->resolveAdjustedTime($currentEnd, $currentStart, $currentEnd, $endSpec, 'current_end');

        // Sicherheitsnetz: ein Endzeitpunkt darf nie vor dem Start liegen.
        if ($nextEnd < $nextStart) {
            $nextEnd = $nextStart;
        }

        $this->writeDateTimeProperty($event, 'DTSTART', $nextStart);
        $this->writeDateTimeProperty($event, 'DTEND', $nextEnd);

        if (isset($event->originalEvent->DURATION)) {
            $duration = max(0, $nextEnd->getTimestamp() - $nextStart->getTimestamp());
            $event->originalEvent->DURATION = $this->formatDurationSeconds($duration);
        }
    }

    /**
     * Ermittelt den neuen Zeitpunkt für Start oder Ende.
     *
     * Die Funktion ist absichtlich klein gehalten:
     * - Referenz auswählen
     * - Offset optional anwenden
     * - bei ungültigem Offset die Referenz unverändert übernehmen
     *
     * @param array<string, mixed> $spec
     */
    private function resolveAdjustedTime(\DateTimeImmutable $fallback, \DateTimeImmutable $currentStart, \DateTimeImmutable $currentEnd, array $spec, string $defaultReference): \DateTimeImmutable
    {
        $reference = strtolower(trim((string) ($spec['reference'] ?? $defaultReference)));
        $offset = trim((string) ($spec['offset'] ?? ''));
        $base = match ($reference) {
            'current_start' => $currentStart,
            'current_end' => $currentEnd,
            default => $fallback,
        };

        // Ohne Offset wird nur die Referenzzeit übernommen.
        if ($offset === '') {
            return $base;
        }

        if (!preg_match('/^([+-]?)(\d+)(s|m|h)$/', $offset, $matches)) {
            // Ungültige Offsets werden defensiv ignoriert.
            return $base;
        }

        $sign = $matches[1] === '-' ? '-' : '+';
        $amount = (int) $matches[2];
        $unit = $matches[3];
        $modifier = sprintf('%s%d %s', $sign, $amount, match ($unit) {
            's' => 'seconds',
            'm' => 'minutes',
            'h' => 'hours',
        });

        return $base->modify($modifier) ?: $base;
    }

    private function parseDateTimeProperty(CalendarEvent $event, string $property): ?\DateTimeImmutable
    {
        if (!isset($event->originalEvent->{$property})) {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $event->originalEvent->{$property});
        } catch (\Exception) {
            return null;
        }
    }

    private function writeDateTimeProperty(CalendarEvent $event, string $property, \DateTimeImmutable $dateTime): void
    {
        if (!isset($event->originalEvent->{$property})) {
            return;
        }

        // Das Zielformat bleibt am ursprünglichen Property-Typ orientiert:
        // - DATE bleibt DATE
        // - UTC-Timestamps bleiben UTC
        // - ansonsten lokales DATE-TIME
        $template = (string) $event->originalEvent->{$property};
        if (preg_match('/^\d{8}$/', $template) === 1) {
            $event->originalEvent->{$property} = $dateTime->format('Ymd');
            return;
        }

        if (preg_match('/Z$/', $template) === 1) {
            $event->originalEvent->{$property} = $dateTime->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\\THis\\Z');
            return;
        }

        // Standardfall: lokales DateTime ohne Zulu-Suffix.
        $event->originalEvent->{$property} = $dateTime->format('Ymd\\THis');
    }

    /**
     * Formatiert eine Dauer in Sekunden als ICS-taugliches ISO-8601-String.
     * Beispiel: 5400 => PT1H30M
     */
    private function formatDurationSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        return sprintf('PT%s%s%s', $hours > 0 ? $hours . 'H' : '', $minutes > 0 ? $minutes . 'M' : '', $seconds > 0 || ($hours === 0 && $minutes === 0) ? $seconds . 'S' : '');
    }

    /**
     * Verschiebt DTSTART oder DTEND anhand eines relativen DateTime-Modifiers.
     *
     * Diese Variante ist bewusst einfacher als adjust_times, weil nur ein
     * einzelnes Feld verändert wird.
     *
     * @param array<string, mixed> $transform
     */
    private function modifyDateTime(CalendarEvent $event, array $transform): void
    {
        $field = strtolower(trim((string) ($transform['field'] ?? '')));
        $modifier = trim((string) ($transform['value'] ?? ''));
        if ($modifier === '') {
            return;
        }

        $property = match ($field) {
            'start' => 'DTSTART',
            'end' => 'DTEND',
            default => null,
        };

        if ($property === null || !isset($event->originalEvent->{$property})) {
            return;
        }

        try {
            $current = new \DateTimeImmutable((string) $event->originalEvent->{$property});
            $next = $current->modify($modifier);
            if ($next === false) {
                return;
            }

            $this->writeDateTimeProperty($event, $property, $next);
        } catch (\Exception) {
        }
    }

    private function removeProperty(CalendarEvent $event, string $field): void
    {
        // Freie Property-Namen werden als ICS-Keys behandelt.
        $property = strtoupper($field);
        if (isset($event->originalEvent->{$property})) {
            unset($event->originalEvent->{$property});
        }
    }

    private function isAllDayEvent(CalendarEvent $event): bool
    {
        // All-Day-Events sind als VALUE=DATE kodiert und haben kein DateTime.
        return isset($event->originalEvent->DTSTART) && preg_match('/^\d{8}$/', (string) $event->originalEvent->DTSTART) === 1;
    }

    private function replaceRegex(string $subject, string $pattern, string $replacement): string
    {
        if ($pattern === '') {
            return $subject;
        }

        // Ungültige Pattern führen nicht zu einem Fehler, sondern lassen den
        // Originalwert unverändert.
        $result = @preg_replace($pattern, $replacement, $subject);
        return is_string($result) ? $result : $subject;
    }
}
