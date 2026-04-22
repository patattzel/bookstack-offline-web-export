<?php

namespace OfflineWebExport\Controllers;

use BookStack\Entities\Queries\BookQueries;
use BookStack\Http\Controller;
use OfflineWebExport\OfflineExportBuilder;
use Throwable;

class BookOfflineExportController extends Controller
{
    public function __construct(
        protected BookQueries $queries,
        protected OfflineExportBuilder $builder,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function export(string $bookSlug)
    {
        $book = $this->queries->findVisibleBySlugOrFail($bookSlug);
        $zip = $this->builder->buildForBook($book);

        return $this->download()->streamedFileDirectly($zip, $book->slug . '-offline-web.zip', true);
    }
}
