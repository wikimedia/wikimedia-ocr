<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\ImageContext;

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
    public function __construct(string $keyFile)
    {
        $this->imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFile]);
    }

    /**
     * Get transcribed text from the given image.
     * @param string $imageUrl
     * @param string|null $lang
     * @return string
     * @throws OcrException
     */
    public function getText(string $imageUrl, ?string $lang = null): string
    {
        $this->checkImageUrl($imageUrl);

        $imageContext = new ImageContext();
        if (null !== $lang && 'en' !== $lang) {
            $imageContext->setLanguageHints([$lang]);
        }

        $response = $this->imageAnnotator->textDetection($imageUrl, ['imageContext' => $imageContext]);

        if ($response->getError()) {
            throw new OcrException('google-error', [$response->getError()->getMessage()]);
        }

        return $response->getFullTextAnnotation()->getText();
    }
}
