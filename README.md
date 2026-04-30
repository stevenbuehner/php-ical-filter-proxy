# iCal Proxy & Filter Service

## 1. Ziel des Projekts
Dieses Projekt stellt einen leichtgewichtigen iCal-Proxy bereit, der externe ICS-Quellen lädt, optional filtert und transformiert, zusammenführt, dedupliziert und als eigene geschützte Feeds ausliefert.

Hauptziele:
- mehrere Quellen pro Export unterstützen
- robuste Filterregeln (`keep` / `remove`)
- optionale Event-Transformationen
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
bash -c "$(curl -fsSL https://raw.githubusercontent.com/stevenbuehner/php-ical-filter-proxy/main/ct/php-ical-filter-proxy.sh)"
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
GET /feed/{slug}/{token}.ics
```

Beispiel:
```text
http://127.0.0.1:8080/feed/technikdienst/random-secret-token.ics
```

## 6. Vollständige YAML-Referenz
```yaml
sources:
  source_key:
    label: "Optionaler Anzeigename"
    url: "https://example.com/calendar.ics"
    cache_ttl: "15m"
    filters:
      - name: "Source Filter"
        action: remove
        match:
          summary:
            contains: "Intern"

exports:
  export_key:
    title: "Export Titel"
    slug: "export-slug"
    token: "random-secret-token"
    cache_ttl: "10m"
    include_sources:
      - source: source_key
        filters:
          - name: "Export Filter"
            action: keep
            match:
              summary:
                contains: "Technik"
            transforms:
              - field: summary
                action: prefix
                value: "[Tech] "
```

## 7. Erklärung Sources
`sources` definiert externe Eingangsfeeds.

Pro Source:
- `label` optional
- `url` Pflicht
- `cache_ttl` optional (Format: `30s`, `15m`, `1h`, `1d`)
- `filters` optional (werden vor Export-Ebene angewendet)

## 8. Erklärung Exports
`exports` definiert auszugebende Zielfeeds.

Pro Export:
- `title` Pflicht
- `slug` Pflicht (öffentlich sichtbarer URL-Teil)
- `token` Pflicht (Zugriffsschutz)
- `cache_ttl` optional
- `include_sources` Pflicht (mindestens eine referenzierte Source)

## 9. Erklärung Source-Filter
Source-Filter leben unter `sources.<key>.filters` und betreffen nur diese einzelne Quelle, bevor sie in Exporte eingeht.

## 10. Erklärung Export-Filter
Export-Filter leben unter `exports.<key>.include_sources[].filters` und werden pro inkludierter Quelle im Kontext eines Exports angewendet.

## 11. Erklärung action keep/remove
- `action: remove`: entferne alle Events, die matchen
- `action: keep`: entferne alle Events, die **nicht** matchen
- fehlt `action`, wird `remove` verwendet

Regeln werden strikt in YAML-Reihenfolge ausgeführt.

## 12. Erklärung Match-Operatoren
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

Beispiele pro Operator:

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

## 13. Erklärung Transformations
Transformationen laufen nach erfolgreichem Match einer Regel.

Unterstützt:
- Textfelder (`summary`, `description`, `location`, `url`): `prefix`, `suffix`, `replace`, `replace_regex`, `remove`
- Kategorien: `add`, `remove`
- Datum: `start.modify`, `end.modify`

Hinweis:
- Bei `action: keep` sind Transformationen typischerweise relevant
- Bei `action: remove` werden gematchte Events entfernt, daher ist Transform dort praktisch meist ohne Effekt

## 14. Caching-Konzept
Zwei Ebenen:
- Source-Cache (`var/cache/feeds`): rohe ICS-Inhalte pro externer Quelle
- Export-Cache (`var/cache/exports`): fertige serialisierte Export-Feeds

Fallback-Verhalten:
- bei HTTP-Fehlern wird (wenn vorhanden) veralteter Source-Cache verwendet
- wenn keine Quelle erfolgreich verarbeitet werden kann, liefert der HTTP-Endpunkt `503`

## 15. CLI-Befehle
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

## 16. Fehlerbehandlung
- Konfigurationsfehler: hart abbrechen
- Runtime-Fehler einzelner Quellen: loggen und nach Möglichkeit mit anderen Quellen fortfahren
- Ungültige Quellen blockieren nicht automatisch den gesamten Export
- HTTP-Endpunkt gibt keine sensiblen Interna aus

## 17. Geplante Admin-GUI
Die Struktur ist bereits auf spätere GUI-Erweiterung vorbereitet:
- serialisierbare DTOs
- klar getrennte Layer (Config, Calendar, Filter, Cache, Http)
- bestehende CLI-Funktionen als Grundlage für GUI-Aktionen

## 18. Sicherheitshinweise zu Tokens
- Tokens schützen öffentliche Feed-URLs
- Tokens niemals in Logs, Tickets oder Screenshots teilen
- pro Export unterschiedliche, starke, zufällige Tokens nutzen
- kompromittierte Tokens sofort rotieren
- bei ungültigem `slug/token` wird bewusst `404` geliefert, um Exporte nicht zu leaken
