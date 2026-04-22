# BookStack Offline Web Export

Standalone BookStack theme module that adds an additional offline web export as a ZIP containing HTML files, images, and attachments.

## Features

- Additional browser routes for `offline-zip`
- Offline ZIP with `index.html`
- Navigable HTML files for books, chapters, and pages
- Bundled images and attachments under `files/`
- View override to add an `Offline Web ZIP` export menu entry

## Installation

1. Ensure BookStack is already running with an active theme:

   ```env
   APP_THEME=custom
   ```

2. Install the module ZIP:

   ```bash
   php artisan bookstack:install-module path/to/bookstack-offline-web-export.zip
   ```

   Alternatively, copy the module folder manually to `themes/<APP_THEME>/modules/offline-web-export`.

3. Clear the view cache if needed:

   ```bash
   php artisan view:clear
   ```

## Direct Install via `bookstack:install-module`

Preferred install method:

```bash
php artisan bookstack:install-module https://github.com/patattzel/bookstack-offline-web-export/releases/latest/download/bookstack-offline-web-export.zip
```

Requirements:

- an active BookStack theme already configured
- a published GitHub release containing `bookstack-offline-web-export.zip`

BookStack expects a real module ZIP with `bookstack-module.json` at the ZIP root.
The normal GitHub repository source ZIP is **not** suitable for `bookstack:install-module`.

## Export Routes

- `/books/{bookSlug}/export/offline-zip`
- `/books/{bookSlug}/chapter/{chapterSlug}/export/offline-zip`
- `/books/{bookSlug}/page/{pageSlug}/export/offline-zip`

## Repository Release ZIP

This repository also contains a GitHub workflow at `.github/workflows/release-module-zip.yml`.
On release publication or manual workflow dispatch it builds `dist/bookstack-offline-web-export.zip`
via `scripts/package-module.sh` and uploads it as both a workflow artifact and a release asset.

## Notes

- BookStack's existing `Portable ZIP` export remains unchanged.
- This module only adds an additional offline web export.
- The module classes intentionally avoid Composer autoloading so the package can be installed directly as a BookStack module.
