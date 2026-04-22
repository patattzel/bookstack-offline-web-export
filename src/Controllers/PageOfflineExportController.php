<?php

namespace OfflineWebExport\Controllers;

use BookStack\Entities\Queries\PageQueries;
use BookStack\Http\Controller;
use OfflineWebExport\OfflineExportBuilder;
use Throwable;

class PageOfflineExportController extends Controller
{
    public function __construct(
        protected PageQueries $queries,
        protected OfflineExportBuilder $builder,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function export(string $bookSlug, string $pageSlug)
    {
        $page = $this->queries->findVisibleBySlugsOrFail($bookSlug, $pageSlug);
        $zip = $this->builder->buildForPage($page);

        return $this->download()->streamedFileDirectly($zip, $page->slug . '-offline-web.zip', true);
    }
}
