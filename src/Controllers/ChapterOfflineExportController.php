<?php

namespace OfflineWebExport\Controllers;

use BookStack\Entities\Queries\ChapterQueries;
use BookStack\Http\Controller;
use OfflineWebExport\OfflineExportBuilder;
use Throwable;

class ChapterOfflineExportController extends Controller
{
    public function __construct(
        protected ChapterQueries $queries,
        protected OfflineExportBuilder $builder,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function export(string $bookSlug, string $chapterSlug)
    {
        $chapter = $this->queries->findVisibleBySlugsOrFail($bookSlug, $chapterSlug);
        $zip = $this->builder->buildForChapter($chapter);

        return $this->download()->streamedFileDirectly($zip, $chapter->slug . '-offline-web.zip', true);
    }
}
