# AGENTS.md
## Coding-Richtlinien für iCal Proxy Projekt

## 1. Grundprinzipien

- Schreibe klaren, lesbaren und wartbaren Code.
- Bevorzuge Einfachheit gegenüber Cleverness.
- Jede Klasse hat genau eine Verantwortung (Single Responsibility).
- Vermeide globale Zustände.
- Nutze Dependency Injection.
- Schreibe deterministischen Code (keine versteckten Side-Effects).

---

## 2. PHP-Richtlinien

- PHP Version: >= 8.3
- `declare(strict_types=1);` ist Pflicht in jeder Datei
- Nutze typed properties und return types konsequent
- Verwende readonly properties wo sinnvoll
- Keine gemischten Typen ohne Grund
- Vermeide `mixed`, außer es ist absolut notwendig

### Beispiel

```php
final class FeedFetcher
{
    public function fetch(string $url): string
    {
        // ...
    }
}
```

---

## 3. Architektur

### Schichten

- Config (YAML + DTOs)
- Domain (Calendar, Filter, Merge)
- Infrastructure (HTTP, Cache)
- Interface (HTTP Controller, CLI)

### Regeln

- Keine Logik im Controller
- Keine direkte Nutzung von YAML außerhalb Config-Layer
- Filter-Logik ist vollständig isoliert
- Cache ist austauschbar (Interface!)

---

## 4. Naming Conventions

- Klassen: PascalCase
- Methoden: camelCase
- Variablen: camelCase
- Konstanten: UPPER_CASE

### Beispiele

- `CalendarMerger`
- `filterEvents()`
- `$eventList`
- `CACHE_TTL_DEFAULT`

---

## 5. Fehlerbehandlung

- Verwende Exceptions, keine stillen Fehler
- Keine leeren catch-Blöcke
- Unterschied zwischen:
  - Config Errors → hart abbrechen
  - Runtime Errors → loggen + fallback

---

## 6. Logging

- Logge alle externen IO-Operationen:
  - HTTP Requests
  - Cache Hits/Misses
- Keine sensiblen Daten loggen (Tokens!)

---

## 7. Performance

- Arbeite möglichst streaming-basiert
- Vermeide unnötige Kopien von großen Arrays
- Filtere früh (vor Merge)
- Verwende HashMaps für Deduplikation

---

## 8. Filter-Engine Regeln

- Regeln werden strikt sequenziell ausgeführt
- action: remove → löscht passende Events
- action: keep → löscht alle NICHT passenden Events
- Default: remove

---

## 9. Tests

- Jede Core-Komponente muss testbar sein
- Keine echten HTTP-Calls in Tests
- Nutze Fixtures für ICS-Daten

---

## 10. Git-Richtlinien

### Commit-Regeln

- Sprache: Deutsch
- Präsens verwenden
- Kurz + präzise
- Eine Änderung pro Commit

### Beispiele

```
feat: füge FilterEngine hinzu
fix: behebe Cache TTL Berechnung
refactor: vereinfache MatchEvaluator Logik
test: ergänze Tests für Deduplicator
docs: ergänze README um YAML Beispiel
```

### Commit-Typen

- feat: neue Funktion
- fix: Bugfix
- refactor: Code-Verbesserung ohne Verhaltenänderung
- test: Tests
- docs: Dokumentation
- chore: Build / Infrastruktur

---

## 11. Branching

- main → stabil
- develop → Integration
- feature/* → neue Features
- fix/* → Bugfixes

---

## 12. Code Review Regeln

- Keine Logik ohne Tests
- Keine Magic Strings
- Keine unnötigen Abhängigkeiten
- Verständlichkeit > Mikro-Optimierung

---

## 13. Sicherheit

- Tokens niemals loggen
- Keine offenen Endpunkte ohne Auth
- Validiere alle Eingaben

---

## 14. Erweiterbarkeit

- Code muss GUI-fähig sein
- DTOs müssen serialisierbar sein
- Keine enge Kopplung an YAML

---

## 15. Done Definition

Ein Feature gilt als fertig wenn:

- Code implementiert
- Tests vorhanden
- Tests grün
- Config validierbar
- Dokumentation aktualisiert
