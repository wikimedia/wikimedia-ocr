<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use Krinkle\Intuition\Intuition;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranskribusEngine extends EngineBase
{
    /** @var string The API key. */
    protected $key;

    /** @var TranskribusClient */
    protected $transkribusClient;

    /**
     * TranskribusEngine constructor.
     * @param TranskribusClient $transkribusClient.
     * @param Intuition $intuition
     * @param string $projectDir
     * @param HttpClientInterface $httpClient
     */
    public function __construct(
        TranskribusClient $transkribusClient,
        Intuition $intuition,
        string $projectDir,
        HttpClientInterface $httpClient
    ) {
        parent::__construct($intuition, $projectDir, $httpClient);
        
        $this->transkribusClient = $transkribusClient;
    }

    /**
     * @inheritDoc
     */
    public static function getId(): string
    {
        return 'transkribus';
    }

    /**
     * @inheritDoc
     * @throws OcrException
     */
    public function getResult(
        string $imageUrl,
        string $invalidLangsMode,
        array $crop,
        ?array $langs = null
    ): EngineResult {
        $this->checkImageUrl($imageUrl);

        [ $validLangs, $invalidLangs ] = $this->filterValidLangs($langs, $invalidLangsMode);

        $image = $this->getImage($imageUrl, $crop);
        $imageUrl = $image->getUrl();
        $response = $this->transkribusClient->initProcess($imageUrl);

        if ($validLangs) {
            $response->setLanguages($this->getLangCodes($validLangs));
        }

        if ($response->hasError()) {
            throw new OcrException('transkribus-error', [$response->getErrorMessage()]);
        }

        $counter = 0;
        while (!$response->hasError() && '' === $response->getTextResult() && 11 >$counter) {
            $response->retrieveProcessStatus();
            $counter++;
            sleep(5);
        }

        if ($response->hasError()) {
            throw new OcrException('transkribus-error', [$response->getErrorMessage()]);
        }

        $resText = $response->getTextResult();
        $warnings = $invalidLangs ? [ $this->getInvalidLangsWarning($invalidLangs) ] : [];
        return new EngineResult($resText, $warnings);
    }
}
