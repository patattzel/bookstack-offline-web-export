<?php

namespace Tests\Themes\OfflineWebExport;

use BookStack\Entities\Tools\HierarchyTransformer;
use OfflineWebExport\OfflineExportBuilder;
use Tests\TestCase;
use ZipArchive;

require_once dirname(__DIR__) . '/src/OfflineExportAssetStore.php';
require_once dirname(__DIR__) . '/src/OfflineExportHtmlRewriter.php';
require_once dirname(__DIR__) . '/src/OfflineExportBuilder.php';

class OfflineExportBuilderTest extends TestCase
{
    public function test_book_export_includes_pages_from_a_promoted_chapter(): void
    {
        $editor = $this->users->editor();
        ['chapter' => $chapter, 'page' => $page] = $this->entities->createChainBelongingToUser($editor);
        $this->actingAs($editor);
        $page->html = '<p>Promoted chapter page body</p>';
        $page->save();

        $book = $this->app->make(HierarchyTransformer::class)->transformChapterToBook($chapter);
        $page->refresh();

        // Keep the affected representation explicit so this remains a regression
        // test even if BookStack later changes how new conversions are stored.
        $page->chapter_id = 0;
        $page->save();

        $zipPath = $this->app->make(OfflineExportBuilder::class)->buildForBook($book);
        $zip = new ZipArchive();

        try {
            $this->assertTrue($zip->open($zipPath) === true);
            $pagePath = 'pages/' . $page->slug . '.html';
            $bookPath = 'books/' . $book->slug . '.html';

            $this->assertNotFalse($zip->locateName($pagePath));
            $this->assertStringContainsString('Promoted chapter page body', $zip->getFromName($pagePath));
            $this->assertStringContainsString('../' . $pagePath, $zip->getFromName($bookPath));
        } finally {
            $zip->close();
            @unlink($zipPath);
        }
    }
}
