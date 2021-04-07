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

    public function getText(string $imageUrl, ?string $lang = null): string
    {
        // Check the URL and fetch the image data.
        $this->checkImageUrl($imageUrl);
        $imageResponse = $this->httpClient->request('GET', $imageUrl);
        try {
            $imageContent = $imageResponse->getContent();
        } catch (ClientException $exception) {
            throw new OcrException('image-retrieval-failed', [$exception->getMessage()]);
        }

        // Sanitize the language code.
        $cleanLang = preg_replace('/[a-zA-Z]+/', '', $lang);

        // Run OCR.
        $ocr = new TesseractOCR();
        $ocr->imageData($imageContent, $imageResponse->getHeaders()['content-length'][0]);
        if ($cleanLang) {
            $ocr->lang($cleanLang);
        }
        $text = $ocr->run();
        return $text;
    }
}
