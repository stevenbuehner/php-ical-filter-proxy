# iCal Proxy & Filter Service

## 1. Ziel des Projekts
Dieses Projekt stellt einen leichtgewichtigen iCal-Proxy bereit. Er lädt externe ICS-Quellen, filtert und transformiert Events optional, führt mehrere Quellen zusammen, dedupliziert sie und liefert daraus geschützte Feeds aus.

Hauptziele:
- mehrere Quellen pro Export unterstützen
- robuste Filterregeln mit klaren Reaktionen auf Treffer
- optionale Event-Transformationen pro Filterregel
- Source- und Export-Cache
- CLI-Werkzeuge für Betrieb und Debugging
- Erweiterbarkeit für spätere Admin-GUI

## 2. Installation
Voraussetzungen:
- PHP 8.3+
- Composer

Installation:
```bash
composer install
```

## 3. Proxmox VE Installation (Direkt)
Für die direkte Installation als LXC auf einem Proxmox-Host:

```bash
bash -c "$(curl -fsSL https://raw.githubusercontent.com/stevenbuehner/php-ical-filter-proxy/refs/heads/master/proxmox/php-ical-filter-proxy.sh)"
```

Standardwerte des CT-Skripts:
- Debian 13
- Unprivileged LXC
- 1 vCPU
- 1024 MB RAM
- 8 GB Disk

Update im Container ausführen:

```bash
/opt/php-ical-filter-proxy/scripts/update.sh
```

## 4. Lokale Entwicklung
HTTP-Server starten:
```bash
php -S 127.0.0.1:8080 -t public
```

Konfiguration prüfen:
```bash
php bin/console app:config:validate
```

## 5. Beispiel für Kalender-Export-URL
Öffentlicher Feed-Endpunkt:
```text
GET /feed/{SECRET}/{SLUG}.ics
```

Beispiel:
```text
http://127.0.0.1:8080/feed/random-secret-token/technikdienst.ics
```

## 6. Vollständige YAML-Referenz
Die Konfiguration ist hierarchisch aufgebaut:
- `sources` beschreibt die Eingangsfeeds
- `exports` beschreibt die auszuliefernden Feeds
- unter beiden Bereichen können `filters` definiert werden

Grundform:
```yaml
sources:
  source_key:
    label: "Optionaler Anzeigename"
    url: "https://example.com/calendar.ics"
    cache_ttl: "15m"
    filters:
      - type: match
        match:
          summary:
            contains: "Intern"
        on_match: remove

exports:
  export_key:
    title: "Export Titel"
    slug: "export-slug"
    token: "random-secret-token"
    cache_ttl: "10m"
    include_sources:
      - source: source_key
        filters:
          - type: match
            match:
              summary:
                contains: "Technik"
            on_match: transform
            transform:
              - type: prefix_text
                field: summary
                value: "[Tech] "
```

Wichtig:
- `sources` wird zuerst geladen
- `exports` referenziert einzelne Sources über `include_sources`
- Filter werden in YAML-Reihenfolge ausgeführt
- `on_match` entscheidet, was nach einem Treffer passiert
- `transform` wird nur genutzt, wenn `on_match: transform` gesetzt ist

## 7. Der Regel-Dreischritt
Eine Filterregel besteht immer aus drei Ebenen:

1. `type` bzw. der Filtertyp
2. `on_match` bzw. das Verhalten bei Treffer
3. `transform` bzw. die optionalen Veränderungen

Beispiel:
```yaml
filters:
  - type: match
    match:
      summary:
        contains: "Technik"
    on_match: transform
    transform:
      - type: prefix_text
        field: summary
        value: "[Tech] "
```

Das bedeutet:
- `type: match` prüft, ob die Regel greift
- `on_match: transform` sagt, dass bei Treffer transformiert werden soll
- `transform` enthält die konkrete Änderung

## 8. Erklärung Sources
`sources` definiert externe Eingangsfeeds.

Pro Source:
- `label` optional
- `url` Pflicht
- `cache_ttl` optional (Format: `30s`, `15m`, `1h`, `1d`)
- `filters` optional (werden vor Export-Ebene angewendet)

## 9. Erklärung Exports
`exports` definiert auszugebende Zielfeeds.

Pro Export:
- `title` Pflicht
- `slug` Pflicht (öffentlich sichtbarer URL-Teil)
- `token` Pflicht (Zugriffsschutz)
- `cache_ttl` optional
- `include_sources` Pflicht (mindestens eine referenzierte Source)
- `filters` pro Included Source arbeiten mit `type`, `match`, `on_match` und optional `transform`

## 10. Erklärung Source-Filter
Source-Filter leben unter `sources.<key>.filters` und betreffen nur diese einzelne Quelle, bevor sie in Exporte eingeht.

## 11. Erklärung Export-Filter
Export-Filter leben unter `exports.<key>.include_sources[].filters` und werden pro inkludierter Quelle im Kontext eines Exports angewendet.

## 12. Erklärung Filter-Verhalten
- `on_match: remove`: entferne alle Events, die matchen
- `on_match: keep`: behalte Events unverändert
- `on_match: transform`: führe `transform[]` aus und behalte das Event
- `match.any: true`: diese Regel trifft auf jedes Event zu

Regeln werden strikt in YAML-Reihenfolge ausgeführt. Mehrere Bedingungen innerhalb eines `match`-Blocks sind mit `AND` verknüpft.

## 13. Erklärung Match-Operatoren
Ein `match`-Filter prüft ein oder mehrere Felder eines Events. Die Felder werden mit den angegebenen Operatoren verglichen.

Unterstützte Felder:
- `summary`
- `description`
- `location`
- `url`
- `categories`
- `date`

Unterstützte Operatoren:
- `contains`
- `contains_any`
- `contains_all`
- `not_contains`
- `equals`
- `not_equals`
- `regex`
- `empty`

Datumsspezifisch (`date`):
- `from`
- `until`

Unterstützte Datumswerte:
- `now`
- relative Angaben wie `+12 months`, `-7 days`
- absolute Form `YYYY-MM-DD`

Beispiele:

`contains`
```yaml
match:
  summary:
    contains: "Technik"
```

`contains_any`
```yaml
match:
  summary:
    contains_any: ["Technik", "Ton", "Licht"]
```

`contains_all`
```yaml
match:
  summary:
    contains_all: ["Technik", "Probe"]
```

`not_contains`
```yaml
match:
  description:
    not_contains: "intern"
```

`equals`
```yaml
match:
  location:
    equals: "Kirche"
```

```yaml
match:
  categories:
    equals: ["Technik", "Dienst"]
```

`not_equals`
```yaml
match:
  summary:
    not_equals: "Abgesagt"
```

`regex`
```yaml
match:
  summary:
    regex: "/^(Technik|Medien)/i"
```

`empty`
```yaml
match:
  url:
    empty: true
```

Datum (`from`, `until`)
```yaml
match:
  date:
    from: "2026-01-01"
    until: "2026-12-31"
```

```yaml
match:
  date:
    from: "now"
    until: "+12 months"
```

## 14. Erklärung Transformations
Transformationen laufen nach erfolgreichem Match einer Regel und werden als Liste von `type`-Einträgen angegeben.

Unterstützt:
- Textfelder: `prefix_text`, `suffix_text`, `replace_text`, `replace_regex`, `remove_property`
- Kategorien: `categories_add`, `categories_remove`
- Datum: `modify_datetime`
- Zeitverschiebung für Start und Ende in einem Schritt: `adjust_times`

Zeitverschiebung:
- `start.reference` und `end.reference` können `current_start` oder `current_end` sein
- `offset` akzeptiert Sekunden, Minuten und Stunden mit optionalem Vorzeichen, z. B. `+30s`, `-20m`, `+2h`
- fehlende `reference`-Werte werden standardmäßig als `current_start` für `start` und `current_end` für `end` behandelt
- All-Day-Events werden ignoriert
- `DTEND` wird nie vor `DTSTART` geschrieben; falls nötig, wird `DTEND` automatisch auf `DTSTART` korrigiert
- wenn `DURATION` vorhanden ist, wird sie zur neuen Zeitspanne passend neu berechnet

### 14.1 Texttransformationen
Texttransformationen arbeiten auf den Feldern `summary`, `description`, `location` und `url`.

Beispiel:
```yaml
filters:
  - type: match
    match:
      any: true
    on_match: transform
    transform:
      - type: prefix_text
        field: summary
        value: "[Global] "
      - type: suffix_text
        field: summary
        value: " (öffentlich)"
      - type: replace_text
        field: description
        search: "intern"
        replace: "extern"
```

### 14.2 Kategorien
Kategorien werden als Liste bzw. ICS-Property behandelt.

Beispiel:
```yaml
filters:
  - type: match
    match:
      any: true
    on_match: transform
    transform:
      - type: categories_add
        value: "Standard"
      - type: categories_remove
        value: "Entwurf"
```

### 14.3 Zeit und Datum
Es gibt zwei verschiedene Zeit-Transformationen:

- `modify_datetime` verändert `start` oder `end` einzeln
- `adjust_times` berechnet Start und Ende gemeinsam

Beispiel `modify_datetime`:
```yaml
filters:
  - type: match
    match:
      summary:
        contains: "Workshop"
    on_match: transform
    transform:
      - type: modify_datetime
        field: start
        value: "+1 day"
```

Beispiel `adjust_times`:
```yaml
filters:
  - type: match
    match:
      any: true
    on_match: transform
    transform:
      - type: adjust_times
        start:
          reference: current_start
          offset: "-20m"
        end:
          reference: current_start
          offset: "10m"
```

Beispiel mit `current_end` und Stunden:
```yaml
filters:
  - type: match
    match:
      summary:
        contains: "Workshop"
    on_match: transform
    transform:
      - type: adjust_times
        start:
          reference: current_end
          offset: "-1h"
        end:
          reference: current_end
          offset: "+2h"
```

Beispiel mit Sekunden und vorhandener `DURATION`:
```yaml
filters:
  - type: match
    match:
      any: true
    on_match: transform
    transform:
      - type: adjust_times
        start:
          reference: current_start
          offset: "+30s"
        end:
          reference: current_start
          offset: "+90s"
```

### 14.4 Weitere Transformationsarten
- `replace_regex` ersetzt per regulärem Ausdruck
- `remove_property` entfernt eine ICS-Property komplett

Beispiel:
```yaml
filters:
  - type: match
    match:
      any: true
    on_match: transform
    transform:
      - type: replace_regex
        field: description
        pattern: "/\\s+/"
        replacement: " "
      - type: remove_property
        field: url
```

## 15. Event Migration pro Export
Mit `event_migration` können sich überschneidende oder zeitlich nahe Events innerhalb eines Exports zu einem gemeinsamen Termin zusammengeführt werden.

Die Migration läuft:
- nach allen Source-Filtern (`sources.<key>.filters`)
- nach allen Include-Filtern (`exports.<key>.include_sources[].filters`)
- vor finaler Serialisierung

Parameter pro Export:
- `event_migration.enabled` (`bool`, optional, default `false`)
- `event_migration.gap_tolerance` (`string`, optional, default `0s`, z. B. `5m`)
- `event_migration.strategy` (`string`, optional, default `merge_titles_csv`)

Beispiel:
```yaml
exports:
  handball_kinder:
    title: "Handballtermine Kinder"
    slug: "handball-kinder"
    token: "random-secret-token"
    cache_ttl: "10m"
    include_sources:
      - source: ananias_f
      - source: ananias_e
      - source: timjamin_hsg
      - source: danio_e
    event_migration:
      enabled: true
      gap_tolerance: "5m"
      strategy: "merge_titles_csv"
```

Regeln:
- Scope ist exportweit über alle eingebundenen Sources.
- All-day-Events werden getrennt von zeitgebundenen Events behandelt.
- Events werden gruppiert, wenn sie sich überschneiden oder wenn der Abstand kleiner/gleich `gap_tolerance` ist.
- Events ohne `DTEND` werden als 0-Dauer behandelt.

Standardstrategie `merge_titles_csv`:
- `summary`: Titel komma-separiert in zeitlicher Reihenfolge
- `dtstart`: frühester Start
- `dtend`: spätestes Ende
- `location`: bei identischem Wert einmal, sonst eindeutige Werte komma-separiert
- `description`: Inhalte mit Trenner zusammengeführt
- `categories`: Union (eindeutige Kategorien)
- `url`: erste verfügbare URL
- `uid`: deterministisch neu erzeugt

## 16. Caching-Konzept
Zwei Ebenen:
- Source-Cache (`var/cache/feeds`): normalisierte Source-Feeds nach Anwendung von `sources.<id>.filters` inkl. Transformationen
- Export-Cache (`var/cache/exports`): fertige serialisierte Export-Feeds

Fallback-Verhalten:
- bei HTTP-Fehlern wird, wenn vorhanden, veralteter normalisierter Source-Cache verwendet
- wenn keine Quelle erfolgreich verarbeitet werden kann, liefert der HTTP-Endpunkt `503`

## 17. CLI-Befehle
Konfiguration:
```bash
php bin/console app:config:validate
```

Sources anzeigen:
```bash
php bin/console app:sources:list
```

Exports anzeigen:
```bash
php bin/console app:exports:list
```

Source-Cache vorwärmen:
```bash
php bin/console app:feeds:warm-cache
```

Export-Vorschau:
```bash
php bin/console app:export:preview technikdienst --limit=20
php bin/console app:export:preview technikdienst --limit=20 --no-cache
```

Cache löschen:
```bash
php bin/console app:cache:clear
php bin/console app:cache:clear --scope=feeds
php bin/console app:cache:clear --scope=exports
```

Cache aufräumen:
```bash
php bin/console app:cache:prune
php bin/console app:cache:prune --scope=feeds --age=3d
php bin/console app:cache:prune --scope=all --age=12h
```

## 18. Fehlerbehandlung
- Konfigurationsfehler: hart abbrechen
- Runtime-Fehler einzelner Quellen: loggen und nach Möglichkeit mit anderen Quellen fortfahren
- Ungültige Quellen blockieren nicht automatisch den gesamten Export
- HTTP-Endpunkt gibt keine sensiblen Interna aus

## 19. Geplante Admin-GUI
Die Struktur ist bereits auf spätere GUI-Erweiterung vorbereitet:
- serialisierbare DTOs
- klar getrennte Layer (Config, Calendar, Filter, Cache, Http)
- bestehende CLI-Funktionen als Grundlage für GUI-Aktionen

## 20. Sicherheitshinweise zu Tokens
- Tokens schützen öffentliche Feed-URLs
- Tokens niemals in Logs, Tickets oder Screenshots teilen
- pro Export unterschiedliche, starke, zufällige Tokens nutzen
- kompromittierte Tokens sofort rotieren
- bei ungültigem `slug/token` wird bewusst `404` geliefert, um Exporte nicht zu leaken

## 21. Typische Fehlerquellen
- `slug` und `token` müssen eindeutig bzw. nicht leer sein.
- `cache_ttl` muss im Format wie `30s`, `15m`, `1h` oder `1d` angegeben werden.
- `regex`-Pattern müssen gültige PCRE-Ausdrücke sein.
- Bei `adjust_times` sind nur `s`, `m` und `h` als Offsets erlaubt.
- `modify_datetime` verschiebt nur `start` oder `end`, nicht beide gemeinsam.
