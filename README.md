# BookStack Offline Web Export

Separates BookStack-Theme-Modul für einen zusätzlichen Offline-Webexport als ZIP mit HTML-Dateien, Bildern und Anhängen.

## Inhalt

- Zusätzliche Browser-Routen für `offline-zip`
- Offline-ZIP mit `index.html`
- Navigierbare HTML-Dateien für Bücher, Kapitel und Seiten
- Gebündelte Bilder und Anhänge unter `files/`
- View-Override für einen zusätzlichen Menüeintrag `Offline Web ZIP`

## Installation

1. Stelle sicher, dass in BookStack ein Theme aktiv ist:

   ```env
   APP_THEME=custom
   ```

2. Installiere das Modul als ZIP:

   ```bash
   php artisan bookstack:install-module path/to/bookstack-offline-web-export.zip
   ```

   Alternativ kann der Modulordner manuell nach `themes/<APP_THEME>/modules/offline-web-export` kopiert werden.

## Direktinstallation via `bookstack:install-module`

Bevorzugte Installationsmethode:

```bash
php artisan bookstack:install-module https://github.com/patattzel/bookstack-offline-web-export/releases/latest/download/bookstack-offline-web-export.zip
```

Voraussetzungen:

- ein bereits aktives BookStack-Theme
- ein veröffentlichtes GitHub-Release mit `bookstack-offline-web-export.zip`

BookStack erwartet ein echtes Modul-ZIP, bei dem `bookstack-module.json` direkt im ZIP-Root liegt.
Das normale GitHub-Repository-Source-ZIP ist **nicht** für `bookstack:install-module` geeignet.

3. Leere bei Bedarf den View-Cache:

   ```bash
   php artisan view:clear
   ```

## Exportpfade

- `/books/{bookSlug}/export/offline-zip`
- `/books/{bookSlug}/chapter/{chapterSlug}/export/offline-zip`
- `/books/{bookSlug}/page/{pageSlug}/export/offline-zip`

## Repository Release ZIP

Dieses Repo enthält zusätzlich einen GitHub-Workflow unter `.github/workflows/release-module-zip.yml`.
Bei einem Release oder manuellen Workflow-Start erzeugt er automatisch `dist/bookstack-offline-web-export.zip`
über `scripts/package-module.sh` und lädt das ZIP als Artifact beziehungsweise Release-Asset hoch.

## Hinweise

- Das bestehende `Portable ZIP` von BookStack bleibt unverändert.
- Dieses Modul ergänzt nur einen zusätzlichen Offline-Webexport.
- Die Modulklassen werden bewusst ohne Composer-Autoload geladen, damit das Paket direkt als BookStack-Modul funktioniert.
