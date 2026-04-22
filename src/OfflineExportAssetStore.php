<?php

namespace OfflineWebExport;

use BookStack\Uploads\Attachment;
use BookStack\Uploads\FileStorage;
use BookStack\Uploads\Image;
use BookStack\Uploads\ImageService;

class OfflineExportAssetStore
{
    /**
     * @var array<string, string>
     */
    protected array $contentsByPath = [];

    /**
     * @var array<int, string>
     */
    protected array $imagePathsById = [];

    /**
     * @var array<int, string>
     */
    protected array $attachmentPathsById = [];

    public function __construct(
        protected ImageService $imageService,
        protected FileStorage $fileStorage,
    ) {
    }

    public function addImage(Image $image): string
    {
        if (isset($this->imagePathsById[$image->id])) {
            return $this->imagePathsById[$image->id];
        }

        $assetPath = 'files/images/' . basename($image->path);
        $this->contentsByPath[$assetPath] = $this->imageService->getImageData($image);
        $this->imagePathsById[$image->id] = $assetPath;

        return $assetPath;
    }

    public function addAttachment(Attachment $attachment): string
    {
        if ($attachment->external) {
            return $attachment->getUrl();
        }

        if (isset($this->attachmentPathsById[$attachment->id])) {
            return $this->attachmentPathsById[$attachment->id];
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $attachment->getFileName()) ?: 'attachment';
        $assetPath = 'files/attachments/' . $attachment->id . '-' . $safeName;

        $stream = $this->fileStorage->getReadStream($attachment->path);
        $content = $stream ? stream_get_contents($stream) : '';
        if (is_resource($stream)) {
            fclose($stream);
        }

        $this->contentsByPath[$assetPath] = $content ?: '';
        $this->attachmentPathsById[$attachment->id] = $assetPath;

        return $assetPath;
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->contentsByPath;
    }
}
