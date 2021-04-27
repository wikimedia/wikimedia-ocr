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
        // Env vars are passed through by the thiagoalessio/tesseract_ocr package to the tesseract command,
        // but when they're loaded from Symfony's .env they aren't actually available (by design),
        // so we have to load this one manually. We only process one image at a time, so don't benefit from
        // multiple threads. See https://github.com/tesseract-ocr/tesseract/issues/898 for some more info.
        putenv('OMP_THREAD_LIMIT=1');
        $text = $ocr->run();
        return $text;
    }
}
