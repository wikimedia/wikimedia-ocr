<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\ImageContext;
use Google\Cloud\Vision\V1\TextAnnotation;
use Krinkle\Intuition\Intuition;

class GoogleCloudVisionEngine extends EngineBase
{
    /** @var string The API key. */
    protected $key;

    /** @var ImageAnnotatorClient */
    protected $imageAnnotator;

    /**
     * GoogleCloudVisionEngine constructor.
     * @param string $keyFile Filesystem path to the credentials JSON file.
     */
    public function __construct(string $keyFile, Intuition $intuition)
    {
        parent::__construct($intuition);
        $this->imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFile]);
    }

    /**
     * Get transcribed text from the given image.
     * @param string $imageUrl
     * @param string[]|null $langs
     * @return string
     * @throws OcrException
     */
    public function getText(string $imageUrl, ?array $langs = null): string
    {
        $this->checkImageUrl($imageUrl);

        // Validate the languages
        $this->validateLangs($langs);

        $imageContext = new ImageContext();
        if (null !== $langs) {
            $imageContext->setLanguageHints($langs);
        }

        $response = $this->imageAnnotator->textDetection($imageUrl, ['imageContext' => $imageContext]);

        if ($response->getError()) {
            throw new OcrException('google-error', [$response->getError()->getMessage()]);
        }

        $annotation = $response->getFullTextAnnotation();
        return $annotation instanceof TextAnnotation ? $annotation->getText() : '';
    }

    /**
     * Get the valid language codes
     * @return string[]
     */
    public function getValidLangs(): array
    {
        return [
            "af",
            "sq",
            "ar",
            "hy",
            "be",
            "bn",
            "bg",
            "ca",
            "zh",
            "hr",
            "cs",
            "da",
            "nl",
            "en",
            "et",
            "fil",
            "tl",
            "fi",
            "fr",
            "de",
            "el",
            "gu",
            "iw",
            "hi",
            "hu",
            "is",
            "id",
            "it",
            "ja",
            "kn",
            "km",
            "ko",
            "lo",
            "lv",
            "lt",
            "mk",
            "ms",
            "ml",
            "mr",
            "ne",
            "no",
            "fa",
            "pl",
            "pt",
            "pa",
            "ro",
            "ru",
            "ru-PETR1708",
            "sr",
            "sr-Latn",
            "sk",
            "sl",
            "es",
            "sv",
            "ta",
            "te",
            "th",
            "tr",
            "uk",
            "vi",
            "yi",
        ];
    }
}
