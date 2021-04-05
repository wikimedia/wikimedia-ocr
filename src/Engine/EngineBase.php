<?php

namespace App\Engine;

use App\Exception\OcrException;

abstract class EngineBase
{

    /**
     * @param string $imageUrl
     * @param string $lang
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
        $uploadUrl = 'https://upload.wikimedia.org/';
        if (substr($imageUrl, 0, strlen($uploadUrl)) !== $uploadUrl) {
            throw new OcrException('image-url-error', [$uploadUrl]);
        }
    }
}
