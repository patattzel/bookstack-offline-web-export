<?php

use BookStack\Facades\Theme;
use BookStack\Permissions\Permission;
use BookStack\Theming\ThemeEvents;
use Illuminate\Routing\Router;
use OfflineWebExport\Controllers\BookOfflineExportController;
use OfflineWebExport\Controllers\ChapterOfflineExportController;
use OfflineWebExport\Controllers\PageOfflineExportController;

spl_autoload_register(function (string $class): void {
    $prefix = 'OfflineWebExport\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . $relativePath;

    if (file_exists($filePath)) {
        require_once $filePath;
    }
});

Theme::listen(ThemeEvents::ROUTES_REGISTER_WEB, function (Router $router) {
    $middleware = [Permission::ContentExport->middleware(), 'throttle:exports'];

    $router->get('/books/{bookSlug}/export/offline-zip', [BookOfflineExportController::class, 'export'])
        ->middleware($middleware)
        ->name('offline-export.book');

    $router->get('/books/{bookSlug}/chapter/{chapterSlug}/export/offline-zip', [ChapterOfflineExportController::class, 'export'])
        ->middleware($middleware)
        ->name('offline-export.chapter');

    $router->get('/books/{bookSlug}/page/{pageSlug}/export/offline-zip', [PageOfflineExportController::class, 'export'])
        ->middleware($middleware)
        ->name('offline-export.page');
});
