<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use Krinkle\Intuition\Intuition;

abstract class EngineBase
{

    public const ALLOWED_FORMATS = ['png', 'jpeg', 'jpg', 'gif', 'tiff', 'tif', 'webp'];

    /** @var string[] The host names for the images. */
    protected $imageHosts = [];

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
        $regex = "/https?:\/\/($hostRegex)\/.+($formatRegex)$/";
        $matches = preg_match($regex, strtolower($imageUrl));
        if (1 !== $matches) {
            $params = [count($this->getImageHosts()), $this->intuition->listToText($this->getImageHosts())];
            throw new OcrException('image-url-error', $params);
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
