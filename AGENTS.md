# AGENTS.md
## Coding-Richtlinien für iCal Proxy Projekt

Dieses Dokument ist die operative Arbeitsgrundlage für Agents im Repository.
Es beschreibt Soll-Architektur, Dateipfade, Qualitätsregeln und Erweiterungsprinzipien
für den aktuellen Stand des Projekts.

---

## 1. Grundprinzipien

- Schreibe klaren, lesbaren und wartbaren Code.
- Bevorzuge Einfachheit gegenüber Cleverness.
- Jede Klasse hat genau eine Verantwortung (Single Responsibility).
- Vermeide globale Zustände.
- Nutze Dependency Injection.
- Schreibe deterministischen Code (keine versteckten Side-Effects).
- Runtime-Verhalten muss robust sein: einzelne fehlerhafte Quellen dürfen Exporte nicht automatisch komplett blockieren.

---

## 2. PHP- und Stilrichtlinien

- PHP-Version: `>= 8.3`
- `declare(strict_types=1);` ist Pflicht in jeder PHP-Datei.
- Nutze typed properties und return types konsequent.
- Verwende readonly properties, wo sinnvoll.
- Keine gemischten Typen ohne Grund.
- Vermeide `mixed`, außer es ist absolut notwendig.
- Halte dich an PSR-12.

---

## 3. Projektstruktur (Ist-Stand)

```text
.github/workflows/                # CI, nightly, lock-refresh
bin/console                       # Symfony Console Einstieg
config/calendars.example.yaml     # Beispiel-Konfiguration (in Git)
config/calendars.yaml             # Laufzeit-Konfiguration (nicht in Git)
public/index.php                  # HTTP-Einstieg + Routing
src/
  Admin/                          # nur vorbereitete Struktur, keine GUI-Implementierung
  Cache/                          # CacheInterface, FileCache, TTL, KeyBuilder
  Calendar/                       # Parser, Fetcher, Merger, Deduplicator, ExportBuilder
  Command/                        # CLI Commands
  Config/                         # Loader, Validator, DTOs, ValidationError
  Filter/                         # MatchEvaluator, FilterEngine, TransformEngine
  Http/                           # CalendarController, Logger
tests/
  Fixtures/                       # ICS Testdaten
  Unit/                           # PHPUnit Unit-Tests
var/cache/feeds/.gitkeep          # Source-Cache Verzeichnis
var/cache/exports/.gitkeep        # Export-Cache Verzeichnis
var/log/.gitkeep                  # Log-Verzeichnis
```

Wichtig:
- `vendor/` ist niemals versioniert.
- `.phpunit.result.cache` ist niemals versioniert.
- Echte Konfigurationen (`config/calendars.yaml`) bleiben lokal/secret und dürfen nicht committed werden.

---

## 4. Schichtenmodell

- Config: YAML + DTOs + Validierung
- Domain/Calendar: Feed laden, parse, merge, deduplicate, serialisieren
- Filter: Match- und Transformationslogik
- Infrastructure: HTTP-Client, File-Cache, Logging
- Interface: HTTP-Controller, CLI

Regeln:
- Keine Geschäftslogik im Controller.
- Keine direkte YAML-Nutzung außerhalb des Config-Layers.
- Cache bleibt über `CacheInterface` austauschbar.
- Filterregeln werden strikt sequenziell verarbeitet.

---

## 5. Zentrale Konzepte (fachlich)

### 5.1 Feed-Endpunkt
- Endpoint: `GET /feed/{slug}/{token}.ics`
- Token muss exakt passen.
- Bei ungültigem slug/token: immer `404` (kein Leak).
- Wenn keine Quelle erfolgreich verarbeitet wurde: `503`.

### 5.2 Cache-Ebenen
- Source-Cache: externe Feed-Inhalte (`var/cache/feeds`)
- Export-Cache: fertiger serialisierter ICS-Export (`var/cache/exports`)

### 5.3 Filterverhalten
- Reihenfolge: exakt YAML-Reihenfolge
- `action: remove` entfernt matchende Events
- `action: keep` entfernt nicht-matchende Events
- fehlende action => Default `remove`
- leere match-Konfiguration verändert Events nicht und erzeugt Warnung in Stats

### 5.4 Deduplication
- basiert auf `UID`
- Strategie: `first wins`

### 5.5 Transformationen
- laufen nach erfolgreichem Match einer Regel
- wirken auf zugrundeliegendes VEVENT
- fehlende Properties dürfen angelegt werden
- `remove` entfernt Properties sauber

---

## 6. Konfigurationsregeln

- Lokale Laufzeitdatei: `config/calendars.yaml`
- Versionierte Vorlage: `config/calendars.example.yaml`
- Beispiele: `docs/examples/*.yaml`

Vor jeder Nutzung muss Konfiguration validiert werden:
```bash
php bin/console app:config:validate
```

Validator-Output ist strukturiert und enthält:
- Fehlercode
- Message
- Path
- line (best effort)
- expected / found

---

## 7. CLI-Befehle (Ist-Stand)

- `app:config:validate`
- `app:feeds:warm-cache`
- `app:sources:list`
- `app:exports:list`
- `app:export:preview`

Regel:
- CLI darf detailliertere Fehler zeigen als HTTP-Endpunkt.

---

## 8. Logging

- Logger: `src/Http/Logger/FileLogger.php`
- Logpfad: `var/log/app.log`
- JSON-Lines Format

Zu loggende Ereignisse:
- Feed Fetch Start/Ende
- Cache Hit/Miss
- HTTP/Fetch Fehler
- Parse Fehler
- Filterstatistiken
- Dedup-Statistiken
- Exportgenerierung

Keine sensiblen Daten (Tokens) loggen.

---

## 9. Tests und Qualität

- PHPUnit ist Pflicht (`vendor/bin/phpunit`)
- Keine echten HTTP-Calls in Tests
- Nutze Fixtures unter `tests/Fixtures`
- Mindestens folgende Komponenten müssen testbar und getestet sein:
  - ConfigLoader
  - ConfigValidator
  - TtlParser
  - MatchEvaluator (inkl. Operatoren)
  - FilterEngine (keep/remove + Reihenfolge)
  - TransformEngine
  - Deduplicator
  - CalendarMerger

---

## 10. CI/CD Richtlinien

Workflows:
- `.github/workflows/ci.yml`
- `.github/workflows/nightly.yml`
- `.github/workflows/lock-refresh.yml`

Regeln:
- Matrix-Tests für PHP `8.3`, `8.4`, `8.5`
- Vor Tests in CI: `config/calendars.example.yaml` nach `config/calendars.yaml` kopieren
- Lock-Refresh läuft separat (PHP 8.4) via PR-Workflow

---

## 11. Sicherheit

- Tokens niemals loggen.
- Keine offenen Endpunkte ohne Token-Schutz.
- Eingaben validieren.
- Fehlerantworten im HTTP-Endpunkt ohne interne Details.

---

## 12. Erweiterbarkeit (zukünftige Features)

Bei Erweiterungen immer erhalten:
- DTOs serialisierbar halten (GUI-fähig).
- Kein harter Vendor-Lock in Domainlogik.
- Neue Operatoren/Transformationen isoliert in `MatchEvaluator`/`TransformEngine` ergänzen.
- Neue Caches/Storage über Interfaces anbinden.
- Admin-GUI nur in `src/Admin` einführen, ohne Core-Logik dorthin zu verschieben.

Geplante sinnvolle Erweiterungen:
- Recurrence-Expansion (derzeit bewusst nicht aktiv)
- Exportweite Filterstufen zusätzlich zu include-source Filtern
- strukturierteres Logging mit Correlation IDs
- Integrationstests für HTTP-Endpunkt mit Fixture-Feeds

---

## 13. Git-Regeln

Commit-Regeln:
- Sprache: Deutsch
- Präsens verwenden
- Kurz + präzise
- Eine logische Änderung pro Commit

Commit-Typen:
- `feat`, `fix`, `refactor`, `test`, `docs`, `chore`, `ci`

Wichtig:
- Keine lokalen Secrets oder Laufzeitkonfigurationen committen.
- `config/calendars.yaml` bleibt unversioniert.
- Nur Beispiel-/Vorlagendateien für Konfiguration liegen in Git.

