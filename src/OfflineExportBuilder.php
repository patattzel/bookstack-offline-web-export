<?php

namespace OfflineWebExport;

use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Page;
use BookStack\Entities\Tools\PageContent;
use BookStack\Uploads\Attachment;
use BookStack\Uploads\FileStorage;
use BookStack\Uploads\Image;
use BookStack\Uploads\ImageService;
use Throwable;
use ZipArchive;

class OfflineExportBuilder
{
    public function __construct(
        protected ImageService $imageService,
        protected FileStorage $fileStorage,
        protected OfflineExportHtmlRewriter $htmlRewriter,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function buildForPage(Page $page): string
    {
        $page->loadMissing('book', 'chapter', 'attachments');
        $assetStore = new OfflineExportAssetStore($this->imageService, $this->fileStorage);
        $linkMap = $this->buildEntityLinkMap([], [], [$page]);
        $pageHtmlPath = 'pages/' . $page->slug . '.html';

        $entries = [
            'index.html' => $this->buildIndexHtml($page->name, $pageHtmlPath),
            'assets/offline-export.css' => $this->css(),
            $pageHtmlPath => $this->buildPageDocument($page, $linkMap, $assetStore, $pageHtmlPath),
        ];

        return $this->buildZip($entries, $assetStore);
    }

    /**
     * @throws Throwable
     */
    public function buildForChapter(Chapter $chapter): string
    {
        $chapter->loadMissing('book');
        $pages = $chapter->getVisiblePages()->filter(fn (Page $page) => !$page->draft)->values()->all();
        foreach ($pages as $page) {
            $page->loadMissing('attachments');
        }

        $assetStore = new OfflineExportAssetStore($this->imageService, $this->fileStorage);
        $linkMap = $this->buildEntityLinkMap([], [$chapter], $pages);
        $chapterHtmlPath = 'chapters/' . $chapter->slug . '.html';
        $entries = [
            'index.html' => $this->buildIndexHtml($chapter->name, $chapterHtmlPath),
            'assets/offline-export.css' => $this->css(),
            $chapterHtmlPath => $this->buildChapterDocument($chapter, $pages, $linkMap, $assetStore, $chapterHtmlPath),
        ];

        foreach ($pages as $page) {
            $pageHtmlPath = 'pages/' . $page->slug . '.html';
            $entries[$pageHtmlPath] = $this->buildPageDocument($page, $linkMap, $assetStore, $pageHtmlPath);
        }

        return $this->buildZip($entries, $assetStore);
    }

    /**
     * @throws Throwable
     */
    public function buildForBook(Book $book): string
    {
        $book->loadMissing('cover');
        $directPages = $book->directPages()->scopes('visible')->where('draft', '=', false)->orderBy('priority')->get()->all();
        $chapters = $book->chapters()->scopes('visible')->orderBy('priority')->get()->all();
        $chapterPages = [];

        foreach ($chapters as $chapter) {
            $pages = $chapter->getVisiblePages()->filter(fn (Page $page) => !$page->draft)->values()->all();
            $chapterPages[$chapter->id] = $pages;
        }

        $allPages = [...$directPages];
        foreach ($chapterPages as $pages) {
            array_push($allPages, ...$pages);
        }

        foreach ($allPages as $page) {
            $page->loadMissing('attachments');
        }

        $assetStore = new OfflineExportAssetStore($this->imageService, $this->fileStorage);
        $linkMap = $this->buildEntityLinkMap([$book], $chapters, $allPages);
        $bookHtmlPath = 'books/' . $book->slug . '.html';
        $entries = [
            'index.html' => $this->buildIndexHtml($book->name, $bookHtmlPath),
            'assets/offline-export.css' => $this->css(),
            $bookHtmlPath => $this->buildBookDocument($book, $directPages, $chapters, $chapterPages, $linkMap, $assetStore, $bookHtmlPath),
        ];

        foreach ($chapters as $chapter) {
            $chapterHtmlPath = 'chapters/' . $chapter->slug . '.html';
            $entries[$chapterHtmlPath] = $this->buildChapterDocument(
                $chapter,
                $chapterPages[$chapter->id],
                $linkMap,
                $assetStore,
                $chapterHtmlPath
            );
        }

        foreach ($allPages as $page) {
            $pageHtmlPath = 'pages/' . $page->slug . '.html';
            $entries[$pageHtmlPath] = $this->buildPageDocument($page, $linkMap, $assetStore, $pageHtmlPath);
        }

        return $this->buildZip($entries, $assetStore);
    }

    /**
     * @param Book[] $books
     * @param Chapter[] $chapters
     * @param Page[] $pages
     * @return array<string, string>
     */
    protected function buildEntityLinkMap(array $books, array $chapters, array $pages): array
    {
        $map = [];

        foreach ($books as $book) {
            $map[$this->normalizeLocalPath($book->getUrl())] = 'books/' . $book->slug . '.html';
        }

        foreach ($chapters as $chapter) {
            $map[$this->normalizeLocalPath($chapter->getUrl())] = 'chapters/' . $chapter->slug . '.html';
        }

        foreach ($pages as $page) {
            $map[$this->normalizeLocalPath($page->getUrl())] = 'pages/' . $page->slug . '.html';
        }

        return $map;
    }

    /**
     * @param array<string, string> $linkMap
     * @throws Throwable
     */
    protected function buildPageDocument(
        Page $page,
        array $linkMap,
        OfflineExportAssetStore $assetStore,
        string $currentPath,
    ): string {
        $page->html = (new PageContent($page))->render();

        $attachments = [];
        foreach ($page->attachments as $attachment) {
            if ($attachment instanceof Attachment) {
                $attachments[$this->normalizeLocalPath($attachment->getUrl())] = $attachment;
            }
        }

        $images = [];
        foreach (Image::query()->where('uploaded_to', '=', $page->id)->whereIn('type', ['gallery', 'drawio'])->get() as $image) {
            $images[$this->normalizeLocalPath($image->url)] = $image;
            $images[$this->normalizeLocalPath($image->path)] = $image;
        }

        $pageContent = $this->htmlRewriter->rewrite($page->html, $linkMap, $currentPath, $assetStore, $attachments, $images);
        $navigation = [];
        $navigation[] = '<a href="' . e($this->relativePath($currentPath, 'index.html')) . '">Index</a>';
        $bookOfflinePath = $page->book ? ($linkMap[$this->normalizeLocalPath($page->book->getUrl())] ?? null) : null;
        if ($bookOfflinePath) {
            $navigation[] = '<a href="' . e($this->relativePath($currentPath, $bookOfflinePath)) . '">Buch</a>';
        }
        $chapterOfflinePath = $page->chapter ? ($linkMap[$this->normalizeLocalPath($page->chapter->getUrl())] ?? null) : null;
        if ($chapterOfflinePath) {
            $navigation[] = '<a href="' . e($this->relativePath($currentPath, $chapterOfflinePath)) . '">Kapitel</a>';
        }

        $attachmentsHtml = '';
        if ($page->attachments->count() > 0) {
            $items = [];
            foreach ($page->attachments as $attachment) {
                if (!$attachment instanceof Attachment) {
                    continue;
                }

                $assetPath = $assetStore->addAttachment($attachment);
                if (!str_starts_with($assetPath, 'http')) {
                    $assetPath = $this->relativePath($currentPath, $assetPath);
                }

                $items[] = '<li><a href="' . e($assetPath) . '">' . e($attachment->name) . '</a></li>';
            }

            $attachmentsHtml = '<section class="offline-section"><h2>Anhänge</h2><ul>' . implode('', $items) . '</ul></section>';
        }

        return $this->layout(
            $page->name,
            $currentPath,
            '<nav class="offline-nav">' . implode('<span>/</span>', $navigation) . '</nav>'
            . '<main class="offline-content"><article class="offline-section">'
            . '<h1>' . e($page->name) . '</h1>'
            . $pageContent
            . '</article>'
            . $attachmentsHtml
            . '</main>'
        );
    }

    /**
     * @param Page[] $pages
     * @param array<string, string> $linkMap
     */
    protected function buildChapterDocument(
        Chapter $chapter,
        array $pages,
        array $linkMap,
        OfflineExportAssetStore $assetStore,
        string $currentPath,
    ): string {
        $description = $this->htmlRewriter->rewrite(
            $chapter->descriptionInfo()->getHtml(),
            $linkMap,
            $currentPath,
            $assetStore
        );

        $pageListItems = array_map(function (Page $page) use ($currentPath) {
            return '<li><a href="' . e($this->relativePath($currentPath, 'pages/' . $page->slug . '.html')) . '">' . e($page->name) . '</a></li>';
        }, $pages);

        $navigation = ['<a href="' . e($this->relativePath($currentPath, 'index.html')) . '">Index</a>'];
        $bookOfflinePath = $linkMap[$this->normalizeLocalPath($chapter->book->getUrl())] ?? null;
        if ($bookOfflinePath) {
            $navigation[] = '<a href="' . e($this->relativePath($currentPath, $bookOfflinePath)) . '">Buch</a>';
        }

        return $this->layout(
            $chapter->name,
            $currentPath,
            '<nav class="offline-nav">' . implode('<span>/</span>', $navigation) . '</nav>'
            . '<main class="offline-content"><section class="offline-section">'
            . '<h1>' . e($chapter->name) . '</h1>'
            . $description
            . '</section>'
            . '<section class="offline-section"><h2>Seiten</h2><ul>' . implode('', $pageListItems) . '</ul></section>'
            . '</main>'
        );
    }

    /**
     * @param Page[] $directPages
     * @param Chapter[] $chapters
     * @param array<int, Page[]> $chapterPages
     * @param array<string, string> $linkMap
     */
    protected function buildBookDocument(
        Book $book,
        array $directPages,
        array $chapters,
        array $chapterPages,
        array $linkMap,
        OfflineExportAssetStore $assetStore,
        string $currentPath,
    ): string {
        $description = $this->htmlRewriter->rewrite(
            $book->descriptionInfo()->getHtml(),
            $linkMap,
            $currentPath,
            $assetStore
        );

        $coverHtml = '';
        $cover = $book->coverInfo()->getImage();
        if ($cover instanceof Image) {
            $coverPath = $assetStore->addImage($cover);
            $coverHtml = '<p class="offline-cover"><img src="' . e($this->relativePath($currentPath, $coverPath)) . '" alt="' . e($book->name) . '"></p>';
        }

        $directPageItems = array_map(function (Page $page) use ($currentPath) {
            return '<li><a href="' . e($this->relativePath($currentPath, 'pages/' . $page->slug . '.html')) . '">' . e($page->name) . '</a></li>';
        }, $directPages);

        $chapterItems = array_map(function (Chapter $chapter) use ($currentPath, $chapterPages) {
            $pageItems = array_map(function (Page $page) use ($currentPath) {
                return '<li><a href="' . e($this->relativePath($currentPath, 'pages/' . $page->slug . '.html')) . '">' . e($page->name) . '</a></li>';
            }, $chapterPages[$chapter->id] ?? []);

            return '<li><a href="' . e($this->relativePath($currentPath, 'chapters/' . $chapter->slug . '.html')) . '">' . e($chapter->name) . '</a>'
                . '<ul>' . implode('', $pageItems) . '</ul>'
                . '</li>';
        }, $chapters);

        return $this->layout(
            $book->name,
            $currentPath,
            '<nav class="offline-nav"><a href="' . e($this->relativePath($currentPath, 'index.html')) . '">Index</a></nav>'
            . '<main class="offline-content"><section class="offline-section">'
            . '<h1>' . e($book->name) . '</h1>'
            . $coverHtml
            . $description
            . '</section>'
            . '<section class="offline-section"><h2>Direkte Seiten</h2><ul>' . implode('', $directPageItems) . '</ul></section>'
            . '<section class="offline-section"><h2>Kapitel</h2><ul>' . implode('', $chapterItems) . '</ul></section>'
            . '</main>'
        );
    }

    protected function layout(string $title, string $currentPath, string $body): string
    {
        return '<!doctype html>'
            . '<html lang="en">'
            . '<head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . e($title) . '</title>'
            . '<link rel="stylesheet" href="' . e($this->relativePath($currentPath, 'assets/offline-export.css')) . '">'
            . '</head>'
            . '<body class="offline-export-body">'
            . $body
            . '</body>'
            . '</html>';
    }

    protected function buildIndexHtml(string $title, string $targetPath): string
    {
        return '<!doctype html>'
            . '<html lang="en">'
            . '<head>'
            . '<meta charset="utf-8">'
            . '<meta http-equiv="refresh" content="0; url=' . e($targetPath) . '">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . e($title) . '</title>'
            . '<link rel="stylesheet" href="assets/offline-export.css">'
            . '</head>'
            . '<body class="offline-export-body offline-export-index">'
            . '<main class="offline-content"><section class="offline-section">'
            . '<h1>' . e($title) . '</h1>'
            . '<p><a href="' . e($targetPath) . '">Weiter zum Export</a></p>'
            . '</section></main>'
            . '</body>'
            . '</html>';
    }

    /**
     * @param array<string, string> $entries
     */
    protected function buildZip(array $entries, OfflineExportAssetStore $assetStore): string
    {
        foreach ($assetStore->all() as $path => $content) {
            $entries[$path] = $content;
        }

        $zipFile = tempnam(sys_get_temp_dir(), 'offline-web-export-');
        $zip = new ZipArchive();
        $zip->open($zipFile, ZipArchive::OVERWRITE);

        foreach ($entries as $entryPath => $content) {
            $zip->addFromString($entryPath, $content);
        }

        $zip->close();

        return $zipFile;
    }

    protected function normalizeLocalPath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        return rtrim($path, '/') ?: '/';
    }

    protected function relativePath(string $fromPath, string $toPath): string
    {
        $fromParts = explode('/', trim(dirname($fromPath), '/'));
        $toParts = explode('/', trim($toPath, '/'));

        if ($fromParts === ['.'] || $fromParts === ['']) {
            $fromParts = [];
        }

        while (!empty($fromParts) && !empty($toParts) && $fromParts[0] === $toParts[0]) {
            array_shift($fromParts);
            array_shift($toParts);
        }

        return str_repeat('../', count($fromParts)) . implode('/', $toParts);
    }

    protected function css(): string
    {
        return <<<'CSS'
:root {
    color-scheme: light;
    --offline-bg: #f4efe7;
    --offline-paper: #fffdf9;
    --offline-ink: #1d1a16;
    --offline-accent: #9f3a22;
    --offline-border: #d9c8b5;
    --offline-muted: #6b6156;
}

* {
    box-sizing: border-box;
}

body.offline-export-body {
    margin: 0;
    background:
        radial-gradient(circle at top left, rgba(159, 58, 34, 0.08), transparent 28rem),
        linear-gradient(180deg, #f8f3eb 0%, var(--offline-bg) 100%);
    color: var(--offline-ink);
    font-family: Georgia, "Times New Roman", serif;
}

.offline-nav,
.offline-content {
    width: min(72rem, calc(100% - 2rem));
    margin: 0 auto;
}

.offline-nav {
    display: flex;
    gap: 0.6rem;
    align-items: center;
    padding: 1rem 0 0;
    color: var(--offline-muted);
}

.offline-nav a,
.offline-content a {
    color: var(--offline-accent);
}

.offline-content {
    padding: 1rem 0 3rem;
}

.offline-section {
    background: var(--offline-paper);
    border: 1px solid var(--offline-border);
    border-radius: 1rem;
    box-shadow: 0 1rem 2.4rem rgba(29, 26, 22, 0.08);
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.offline-cover img,
.offline-section img {
    max-width: 100%;
    height: auto;
    border-radius: 0.75rem;
}

.offline-section ul {
    padding-left: 1.25rem;
}

.offline-export-index .offline-content {
    min-height: 100vh;
    display: grid;
    place-items: center;
}
CSS;
    }
}
