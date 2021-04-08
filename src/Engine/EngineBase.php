<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;

abstract class EngineBase
{
    public const ALLOWED_FORMATS = ['png', 'jpeg', 'jpg', 'gif', 'tiff', 'tif', 'webp'];

    /**
     * @param string $imageUrl
     * @param string|null $lang
     * @return string
     */
    abstract public function getText(string $imageUrl, ?string $lang = null): string;

    /**
     * Checks that the given image URL is valid.
     * @param string $imageUrl
     * @throws OcrException
     */
    public function checkImageUrl(string $imageUrl): void
    {
        $formatRegex = implode('|', self::ALLOWED_FORMATS);
        $matches = preg_match("/^https?:\/\/upload\.wikimedia\.org\/.*($formatRegex)$/", strtolower($imageUrl));
        if (1 !== $matches) {
            throw new OcrException('image-url-error', ['https://upload.wikimeida.org']);
        }
    }
}
