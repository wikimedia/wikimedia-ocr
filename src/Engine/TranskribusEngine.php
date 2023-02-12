<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use Krinkle\Intuition\Intuition;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranskribusEngine extends EngineBase
{

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

        $image = $this->getImage($imageUrl, $crop);
        $imageUrl = $image->getUrl();
        $processId = $this->transkribusClient->initProcess($imageUrl);

        if (0 === $processId) {
            $message = "Unable to Init Process";
            throw new OcrException('transkribus-error', [$message]);
        }

        sleep(50);
        $resText = $this->transkribusClient->retrieveProcessResult($processId);

        $warnings = [];
        return new EngineResult($resText, $warnings);
    }
}
