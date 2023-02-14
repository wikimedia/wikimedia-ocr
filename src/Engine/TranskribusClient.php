<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use Krinkle\Intuition\Intuition;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranskribusClient
{

    /** @var HttpClientInterface */
    private $httpClient;

    /** @var string transkribus access token. */
    private $accessToken;

    /** @var string transkribus request status. */
    public $processStatus;

    /** @var string Transkribus process URL. */
    private const PROCESSES_URL = "https://transkribus.eu/processing/v1/processes";

    /**
     * TranskribusClient constructor.
     * @param HttpClientInterface $httpClient
     * @param string $accessToken
     */
    public function __construct(
        HttpClientInterface $httpClient,
        string $accessToken
    ) {
        $this->httpClient = $httpClient;
        $this->accessToken = $accessToken;
    }

    /**
     * @param string $imageURL
     * @param int $htrId
     * @return int
     */
    public function initProcess(string $imageURL, int $htrId): int
    {
        $jsonBody = [
            'config' => [
                'textRecognition' => [
                    'htrId' => $htrId,
                ],
            ],
            'image' => [
                'imageUrl' => $imageURL,
            ],
        ];

        $content = $this->request('POST', self::PROCESSES_URL, $jsonBody);

        if ($content->status !== 'FAILED') {
            $processId = $content->processId;
            return $processId;
        }

        throw new OcrException('transkribus-error-init-process-failed');

    }

    /**
     * @param int $processId
     * @return string
     */
    public function retrieveProcessResult(int $processId): string
    {
        $url = self::PROCESSES_URL.'/'.$processId;

        $content = $this->request('GET', $url);
        $textResult = '';

        if ($content->status === 'FAILED') {
            throw new OcrException('transkribus-error-failed-process-status');
        }

        if ($content->status === 'FINISHED') {
            $this->processStatus = $content->status;
            $textResult = $content->content->text ?? '';
        }

        return $textResult;
    }

    /**
     * @param int $statusCode
     */
    private function throwException(int $statusCode): void
    {
        switch ($statusCode) {
            case 0:
                throw new OcrException(
                    'transkribus-error-empty-response'
                );
            case 401:
                throw new OcrException(
                    'transkribus-error-401',
                    [$statusCode]
                );
            default:
                throw new OcrException(
                    'transkribus-error-default',
                    [$statusCode]
                );
        }
    }

    /**
     * @param string $method
     * @param string $url
     * @param mixed[] $jsonBody
     */
    private function request(
        string $method,
        string $url,
        array $jsonBody = []
    ): object {
        $body = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$this->accessToken,
            ],
        ];
        if ([] != $jsonBody) {
            $body['json'] = $jsonBody;
        }

        $response = $this->httpClient->request($method, $url, $body);
        $statusCode = $response->getStatusCode();
        if (200 === $statusCode) {
            $content = json_decode($response->getContent());

            if (!empty($content)) {
                return $content;
            }
            $this->throwException(0);

        } else {
            $this->throwException($statusCode);
        }
    }
}
