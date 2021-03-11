<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use Wikisource\GoogleCloudVisionPHP\GoogleCloudVision;
use Wikisource\GoogleCloudVisionPHP\LimitExceededException;

class GoogleCloudVisionEngine
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
        }

        $this->gcv->addFeatureDocumentTextDetection();
        if (null !== $lang && 'en' !== $lang) {
            $this->gcv->setImageContext(['languageHints' => [$lang]]);
        }
        $response = $this->gcv->request();

        // Check for errors and pass any through.
        $error = $response['responses'][0]['error']['message'] ?? '';
        if ($error) {
            $msg = str_replace($this->key, '[KEY REDACTED]', $error);
            throw new OcrException($msg);
        }

        // Return only the text (it's not an error if there's no text).
        return $response['responses'][0]['textAnnotations'][0]['description'] ?? '';
    }
}
