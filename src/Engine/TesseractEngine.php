<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use thiagoalessio\TesseractOCR\TesseractOCR;

class TesseractEngine extends EngineBase
{

    /** @var HttpClientInterface */
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * @param string $imageUrl
     * @param string[]|null $langs
     * @return string
     */
    public function getText(string $imageUrl, ?array $langs = null): string
    {
        // Check the URL and fetch the image data.
        $this->checkImageUrl($imageUrl);
        $imageResponse = $this->httpClient->request('GET', $imageUrl);
        try {
            $imageContent = $imageResponse->getContent();
        } catch (ClientException $exception) {
            throw new OcrException('image-retrieval-failed', [$exception->getMessage()]);
        }

        // Run OCR.
        $ocr = new TesseractOCR();
        $ocr->imageData($imageContent, $imageResponse->getHeaders()['content-length'][0]);
        if ($langs && count($langs) > 0) {
            $ocr->lang(...$langs);
        }
        $text = $ocr->run();
        return $text;
    }
}
