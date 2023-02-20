<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
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
     * @param string $points
     * @return int
     */
    public function initProcess(string $imageURL, int $htrId, string $points): int
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

        if (!empty($points)) {
            $cropContent = [
                'regions' => array([
                    'id' => 'region_1',
                    'coords' => [
                        'points' => $points,
                    ],
                ]),
            ];
            $jsonBody['content'] = $cropContent;
        }

        $content = $this->request('POST', self::PROCESSES_URL, $jsonBody);

        if ('FAILED' !== $content->status) {
            $processId = $content->processId;
            return $processId;
        }

        throw new OcrException('transkribus-init-process-error');
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

        if ('FAILED' === $content->status) {
            throw new OcrException('transkribus-failed-process-error');
        }

        if ('FINISHED' === $content->status) {
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
                    'transkribus-empty-response-error'
                );
            case 401:
                throw new OcrException(
                    'transkribus-unauthorized-error',
                    [$statusCode]
                );
            default:
                throw new OcrException(
                    'transkribus-default-error',
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
