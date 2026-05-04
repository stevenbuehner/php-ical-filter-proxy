<?php

declare(strict_types=1);

namespace App\Filter\Match;

use DateTimeImmutable;

final readonly class DateMatchEvaluator
{
    /**
     * Prüft Datumsfilter auf Basis von DTSTART.
     *
     * Unterstützt:
     * - `from`: untere Grenze
     * - `until`: obere Grenze
     *
     * Die Werte dürfen absolute Datumsstrings oder relative Angaben wie
     * `+12 months` bzw. `-2 weeks` sein.
     */
    /**
     * @param array<string, mixed> $dateConfig
     */
    public function matches(?string $dtstart, array $dateConfig): bool
    {
        $eventDate = $this->parseEventDate($dtstart);
        if ($eventDate === null) {
            return false;
        }

        if (array_key_exists('from', $dateConfig)) {
            $from = $this->resolveDateToken($dateConfig['from']);
            if ($from === null || $eventDate < $from) {
                return false;
            }
        }

        if (array_key_exists('until', $dateConfig)) {
            $until = $this->resolveDateToken($dateConfig['until']);
            if ($until === null || $eventDate > $until->setTime(23, 59, 59)) {
                return false;
            }
        }

        return true;
    }

    private function parseEventDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function resolveDateToken(mixed $token): ?DateTimeImmutable
    {
        if (!is_string($token) || trim($token) === '') {
            return null;
        }

        $token = trim($token);
        $now = new DateTimeImmutable('now');

        // Die Konfiguration darf auch einfache relative Zeitangaben enthalten.
        if (mb_strtolower($token) === 'now') {
            return $now;
        }

        if (preg_match('/^[+-].+$/', $token) === 1) {
            try {
                return $now->modify($token) ?: null;
            } catch (\Exception) {
                return null;
            }
        }

        $absolute = DateTimeImmutable::createFromFormat('Y-m-d', $token);
        if ($absolute instanceof DateTimeImmutable) {
            return $absolute->setTime(0, 0, 0);
        }

        return null;
    }
}
