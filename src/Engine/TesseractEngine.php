<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use Krinkle\Intuition\Intuition;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use thiagoalessio\TesseractOCR\TesseractOCR;

class TesseractEngine extends EngineBase
{
    /** @var HttpClientInterface */
    private $httpClient;

    /** @var TesseractOCR */
    private $ocr;

    /** @var int Maximum value for page segmentation mode. */
    public const MAX_PSM = 13;

    /** @var int Default value for page segmentation mode. */
    public const DEFAULT_PSM = 3;

    /** @var int Maximum value for OCR engine mde. */
    public const MAX_OEM = 3;

    /** @var int Default value for OCR engine mode. */
    public const DEFAULT_OEM = 3;

    public function __construct(HttpClientInterface $httpClient, Intuition $intuition, TesseractOCR $tesseractOcr)
    {
        parent::__construct($intuition);
        $this->httpClient = $httpClient;
        $this->ocr = $tesseractOcr;
    }

    /**
     * @param string $imageUrl
     * @param string[]|null $langs
     * @return string
     * @throws OcrException
     */
    public function getText(string $imageUrl, ?array $langs = null): string
    {
        // Check the URL and fetch the image data.
        $this->checkImageUrl($imageUrl);

        // Validate the languages
        $this->validateLangs($langs);

        $imageResponse = $this->httpClient->request('GET', $imageUrl);
        try {
            $imageContent = $imageResponse->getContent();
        } catch (ClientException $exception) {
            throw new OcrException('image-retrieval-failed', [$exception->getMessage()]);
        }
        $this->ocr->imageData($imageContent, $imageResponse->getHeaders()['content-length'][0]);
        if ($langs && count($langs) > 0) {
            $this->ocr->lang(...$langs);
        }

        // Env vars are passed through by the thiagoalessio/tesseract_ocr package to the tesseract command,
        // but when they're loaded from Symfony's .env they aren't actually available (by design),
        // so we have to load this one manually. We only process one image at a time, so don't benefit from
        // multiple threads. See https://github.com/tesseract-ocr/tesseract/issues/898 for some more info.
        putenv('OMP_THREAD_LIMIT=1');
        $text = $this->ocr->run();
        return $text;
    }

    /**
     * Get the valid language codes
     * @return string[]
     */
    public function getValidLangs(): array
    {
        return $this->ocr->availableLanguages();
    }

    /**
     * Set the page segmentation mode.
     * @param int $psm
     */
    public function setPsm(int $psm): void
    {
        $this->validateOption('psm', $psm, self::MAX_PSM);
        $this->ocr->psm($psm);
    }

    /**
     * Set the OCR engine mode.
     * @param int $oem
     */
    public function setOem(int $oem): void
    {
        $this->validateOption('oem', $oem, self::MAX_OEM);
        $this->ocr->oem($oem);
    }

    /**
     * Validates the given option.
     * @param string $option
     * @param int $given
     * @param int $maximum
     * @throws OcrException
     */
    private function validateOption(string $option, int $given, int $maximum): void
    {
        if ($given > $maximum) {
            throw new OcrException(
                'tesseract-param-error',
                [
                    $this->intuition->msg("tesseract-$option-label"),
                    $given,
                    $maximum,
                ]
            );
        }
    }
}
