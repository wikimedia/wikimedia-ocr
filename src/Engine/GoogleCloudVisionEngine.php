<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;

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
     * @param string[]|null $langs
     * @return string
     * @throws OcrException
     */
    public function getText(string $imageUrl, ?array $langs = null): string
    {
        $this->checkImageUrl($imageUrl);

        try {
            $this->gcv->setImage($imageUrl);
        } catch (LimitExceededException $e) {
            throw new OcrException('limit-exceeded', [$e->getMessage()]);
        } catch (ErrorException $e) {
            if (false !== strpos($e->getMessage(), '404 Not Found')) {
                // The 'image-retrieval-failed' message is the important part that is localized.
                throw new OcrException('image-retrieval-failed', ['404 Not Found']);
            }

            // Unknown error.
            throw $e;
        }

        $this->gcv->addFeatureDocumentTextDetection();
        if (null !== $langs) {
            $this->gcv->setImageContext(['languageHints' => $langs]);
        }

        $response = $this->imageAnnotator->textDetection($imageUrl, ['imageContext' => $imageContext]);

        if ($response->getError()) {
            throw new OcrException('google-error', [$response->getError()->getMessage()]);
        }

        return $response->getFullTextAnnotation()->getText();
    }
}
