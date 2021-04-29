<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;

abstract class EngineBase
{

    public const ALLOWED_FORMATS = ['png', 'jpeg', 'jpg', 'gif', 'tiff', 'tif', 'webp'];

    /** @var string[] The host names for the images. */
    protected $imageHosts = [];

    /**
     * @param string $imageUrl
     * @param string[]|null $langs
     * @return string
     */
    abstract public function getText(string $imageUrl, ?array $langs = null): string;

    public function setImageHosts(string $imageHosts): void
    {
        $this->imageHosts = array_map('trim', explode(',', $imageHosts));
    }

    /**
     * @return string[]
     */
    public function getImageHosts(): array
    {
        return $this->imageHosts;
    }

    /**
     * Checks that the given image URL is valid.
     * @param string $imageUrl
     * @throws OcrException
     */
    public function checkImageUrl(string $imageUrl): void
    {
        $hostRegex = implode('|', array_map('preg_quote', $this->getImageHosts()));
        $formatRegex = implode('|', self::ALLOWED_FORMATS);
        $regex = "/https?:\/\/($hostRegex).*($formatRegex)$/";
        $matches = preg_match($regex, strtolower($imageUrl));
        if (1 !== $matches) {
            throw new OcrException('image-url-error', [join(', ', $this->getImageHosts())]);
        }
    }
}
