<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use Krinkle\Intuition\Intuition;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use thiagoalessio\TesseractOCR\TesseractOCR;

class TesseractEngine extends EngineBase
{

    /** @var TesseractOCR */
    private $ocr;

    /** @var int Maximum value for page segmentation mode. */
    public const MAX_PSM = 13;

    /** @var int Default value for page segmentation mode. */
    public const DEFAULT_PSM = 3;

    /**
     * TesseractEngine constructor.
     * @param HttpClientInterface $httpClient
     * @param Intuition $intuition
     * @param string $projectDir
     * @param TesseractOCR $tesseractOcr
     */
    public function __construct(
        HttpClientInterface $httpClient,
        Intuition $intuition,
        string $projectDir,
        TesseractOCR $tesseractOcr
    ) {
        parent::__construct($intuition, $projectDir, $httpClient);
        $this->ocr = $tesseractOcr;
    }

    /**
     * @inheritDoc
     */
    public static function getId(): string
    {
        return 'tesseract';
    }

    /**
     * @inheritDoc
     */
    public function getResult(
        string $imageUrl,
        string $invalidLangsMode,
        array $crop,
        ?array $langs = null
    ): EngineResult {
        // Check the URL and fetch the image data.
        $this->checkImageUrl($imageUrl);

        [ $validLangs, $invalidLangs ] = $this->filterValidLangs($langs, $invalidLangsMode);

        $image = $this->getImage($imageUrl, $crop, self::DO_DOWNLOAD_IMAGE);
        $this->ocr->imageData($image->getData(), $image->getSize());

        if ($validLangs) {
            $this->ocr->lang(...$this->getLangCodes($validLangs));
        }

        // Env vars are passed through by the thiagoalessio/tesseract_ocr package to the tesseract command,
        // but when they're loaded from Symfony's .env they aren't actually available (by design),
        // so we have to load this one manually. We only process one image at a time, so don't benefit from
        // multiple threads. See https://github.com/tesseract-ocr/tesseract/issues/898 for some more info.
        putenv('OMP_THREAD_LIMIT=1');
        $text = $this->ocr->run();

        $warnings = $invalidLangs ? [ $this->getInvalidLangsWarning($invalidLangs) ] : [];
        return new EngineResult($text, $warnings);
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
