# Proxmox Installation: PHP Ical Filter Proxy

Diese Anleitung installiert **PHP Ical Filter Proxy** als LXC auf Proxmox VE.

## Voraussetzungen
- Proxmox VE Host mit Internetzugang
- Root-Shell auf dem Proxmox-Host

## Schnellinstallation (Copy & Paste)
Führe den folgenden Befehl direkt auf dem **Proxmox-Host** aus:

```bash
bash -c "$(curl -fsSL https://raw.githubusercontent.com/stevenbuehner/php-ical-filter-proxy/main/proxmox/php-ical-filter-proxy.sh)"
```

Das Skript erstellt den LXC und installiert die Anwendung automatisch.

## Standardwerte des LXC-Skripts
- Debian 13
- Unprivileged Container
- 1 vCPU
- 1024 MB RAM
- 8 GB Disk

## Nach der Installation
- Öffne die ausgegebene URL im Browser (`http://<CT-IP>`)
- Passe die Laufzeitkonfiguration im Container an:
  - `/opt/php-ical-filter-proxy/config/calendars.yaml`

## Update im Container
Im Container ausführen:

```bash
/opt/php-ical-filter-proxy/scripts/update.sh
```

## Skript-Dateien in diesem Ordner
- `proxmox/php-ical-filter-proxy.sh` (Host-seitige LXC-Erstellung)
- `proxmox/php-ical-filter-proxy-install.sh` (Installation im Container)
