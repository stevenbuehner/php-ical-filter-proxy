# iCal Proxy & Filter Service

Leichtgewichtiges PHP-Projekt für das Laden, Filtern, Zusammenführen und Ausliefern von iCal-Feeds.

## Voraussetzungen

- PHP 8.3+
- Composer

## Installation

```bash
composer install
```

## Lokalen HTTP-Server starten

```bash
php -S 127.0.0.1:8080 -t public
```

## Konfiguration validieren

```bash
php bin/console app:config:validate
```
