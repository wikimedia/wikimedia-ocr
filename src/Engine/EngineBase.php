<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use Krinkle\Intuition\Intuition;

abstract class EngineBase
{
    public const ALLOWED_FORMATS = ['png', 'jpeg', 'jpg', 'gif', 'tiff', 'tif', 'webp'];

    /** @var Intuition */
    protected $intuition;

    public function __construct(Intuition $intuition)
    {
        $this->intuition = $intuition;
    }

    /**
     * @param string $imageUrl
     * @param string[]|null $langs
     * @return string
     */
    abstract public function getText(string $imageUrl, ?array $langs = null): string;

     /**
     * @return string[]
     */
    abstract public function getValidLangs(): array;

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
            throw new OcrException('image-url-error', ['https://upload.wikimedia.org']);
        }
    }

    /**
     * @param string[]|null $langs
     * @throws OcrException
     */
    public function validateLangs(?array $langs): void
    {
        $invalidLangs = array_diff($langs, $this->getValidLangs());

        if (count($invalidLangs)) {
            $invalidLangs = array_values($invalidLangs);
            throw new OcrException('langs-param-error', [
                count($invalidLangs),
                $this->intuition->listToText($invalidLangs),
            ]);
        }
    }
}
