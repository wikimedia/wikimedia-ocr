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
    private $intuition;

    /**
     * @param string $imageUrl
     * @param string[]|null $langs
     * @return string
     */
    abstract public function getText(string $imageUrl, ?array $langs = null): string;

    public function setIntuition(Intuition $intuition): void
    {
        $this->intuition = $intuition;
    }

    protected function getIntuition(): Intuition
    {
        return $this->intuition ?? new Intuition();
    }

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
            $params = [
                count($this->getImageHosts()),
                $this->getIntuition()->listToText($this->getImageHosts()),
            ];
            throw new OcrException('image-url-error', $params);
        }
    }
}
