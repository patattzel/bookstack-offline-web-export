<?php

namespace OfflineWebExport;

use BookStack\Uploads\Attachment;
use BookStack\Uploads\Image;
use BookStack\Util\HtmlDocument;
use DOMElement;

class OfflineExportHtmlRewriter
{
    /**
     * @param array<string, string> $linkMap
     * @param array<string, Attachment> $attachmentsByPath
     * @param array<string, Image> $imagesByPath
     */
    public function rewrite(
        string $html,
        array $linkMap,
        string $currentPath,
        OfflineExportAssetStore $assetStore,
        array $attachmentsByPath = [],
        array $imagesByPath = [],
    ): string {
        $document = new HtmlDocument($html);

        foreach ($document->queryXPath('//img[@src]') as $imageNode) {
            if ($imageNode instanceof DOMElement) {
                $this->rewriteImageSource($imageNode, $currentPath, $assetStore, $imagesByPath);
            }
        }

        foreach ($document->queryXPath('//a[@href]') as $anchorNode) {
            if ($anchorNode instanceof DOMElement) {
                $this->rewriteAnchorHref($anchorNode, $linkMap, $currentPath, $assetStore, $attachmentsByPath);
            }
        }

        return $document->getBodyInnerHtml();
    }

    /**
     * @param array<string, Image> $imagesByPath
     */
    protected function rewriteImageSource(
        DOMElement $imageNode,
        string $currentPath,
        OfflineExportAssetStore $assetStore,
        array $imagesByPath,
    ): void {
        $src = trim($imageNode->getAttribute('src'));
        if ($src === '' || str_starts_with($src, 'data:')) {
            return;
        }

        $normalizedPath = $this->normalizeLocalPath($src);
        $image = $imagesByPath[$normalizedPath] ?? null;
        if (!$image) {
            return;
        }

        $assetPath = $assetStore->addImage($image);
        $imageNode->setAttribute('src', $this->relativePath($currentPath, $assetPath));
    }

    /**
     * @param array<string, string> $linkMap
     * @param array<string, Attachment> $attachmentsByPath
     */
    protected function rewriteAnchorHref(
        DOMElement $anchorNode,
        array $linkMap,
        string $currentPath,
        OfflineExportAssetStore $assetStore,
        array $attachmentsByPath,
    ): void {
        $href = trim($anchorNode->getAttribute('href'));
        if ($href === '' || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
            return;
        }

        $fragment = parse_url($href, PHP_URL_FRAGMENT);
        $normalizedPath = $this->normalizeLocalPath($href);

        $attachment = $attachmentsByPath[$normalizedPath] ?? null;
        if ($attachment) {
            $assetPath = $assetStore->addAttachment($attachment);
            if (!str_starts_with($assetPath, 'http')) {
                $assetPath = $this->relativePath($currentPath, $assetPath);
            }

            $anchorNode->setAttribute('href', $assetPath);
            return;
        }

        $targetPath = $linkMap[$normalizedPath] ?? null;
        if (!$targetPath) {
            return;
        }

        $relativePath = $this->relativePath($currentPath, $targetPath);
        if (!empty($fragment)) {
            $relativePath .= '#' . $fragment;
        }

        $anchorNode->setAttribute('href', $relativePath);
    }

    protected function normalizeLocalPath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        if ($path === '' && str_starts_with($url, '/')) {
            $path = $url;
        }

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
}
