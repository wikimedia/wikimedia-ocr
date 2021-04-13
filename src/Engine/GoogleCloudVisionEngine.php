<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use ErrorException;
use Wikisource\GoogleCloudVisionPHP\GoogleCloudVision;
use Wikisource\GoogleCloudVisionPHP\LimitExceededException;

class GoogleCloudVisionEngine extends EngineBase
{
    /** @var string The API key. */
    protected $key;

    /** @var GoogleCloudVision */
    protected $gcv;

    /**
     * GoogleCloudVisionEngine constructor.
     * @param string $endpoint Google Cloud Vision API endpoint.
     * @param string $key Google Cloud Vision API key.
     */
    public function __construct(string $endpoint, string $key)
    {
        $this->key = $key;
        $this->gcv = new GoogleCloudVision();
        $this->gcv->setKey($this->key);
        $this->gcv->setEndpoint($endpoint);
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
        if (null !== $lang && 'en' !== $lang) {
            $this->gcv->setImageContext(['languageHints' => [$lang]]);
        }
        $response = $this->gcv->request();

        // Check for errors and pass any through.
        /* @phan-suppress-next-line PhanTypeMismatchDimFetch */
        $error = $response['responses'][0]['error']['message'] ?? '';
        if ($error) {
            $msg = str_replace($this->key, '[KEY REDACTED]', $error);
            throw new OcrException($msg);
        }

        // Return only the text (it's not an error if there's no text).
        /* @phan-suppress-next-line PhanTypeMismatchDimFetch */
        return $response['responses'][0]['textAnnotations'][0]['description'] ?? '';
    }
}
