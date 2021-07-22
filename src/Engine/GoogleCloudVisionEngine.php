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
     * @param Intuition $intuition
     * @param string $projectDir
     */
    public function __construct(string $keyFile, Intuition $intuition, string $projectDir)
    {
        parent::__construct($intuition, $projectDir);
        $this->imageAnnotator = new ImageAnnotatorClient(['credentials' => $keyFile]);
    }

    /**
     * @inheritDoc
     */
    public static function getId(): string
    {
        return 'google';
    }

    /**
     * @inheritDoc
     * @throws OcrException
     */
    public function getResult(string $imageUrl, string $invalidLangsMode, ?array $langs = null): EngineResult
    {
        $this->checkImageUrl($imageUrl);

        [ $validLangs, $invalidLangs ] = $this->filterValidLangs($langs, $invalidLangsMode);

        $imageContext = new ImageContext();
        if ($validLangs) {
            $imageContext->setLanguageHints($this->getLangCodes($validLangs));
        }

        $response = $this->imageAnnotator->textDetection($imageUrl, ['imageContext' => $imageContext]);

        if ($response->getError()) {
            throw new OcrException('google-error', [$response->getError()->getMessage()]);
        }

        $annotation = $response->getFullTextAnnotation();
        $resText = $annotation instanceof TextAnnotation ? $annotation->getText() : '';
        $warnings = $invalidLangs ? [ $this->getInvalidLangsWarning($invalidLangs) ] : [];
        return new EngineResult($resText, $warnings);
    }
}
