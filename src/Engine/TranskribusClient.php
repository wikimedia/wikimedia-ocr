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
    private $accessToken = '';

    /** @var $config. */
    private $config = [];

    /** @var string Transcribed result. */
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
     * @param mixed[] $config
     * @return int
     */
    public function initProcess(string $imageURL, array $config = []): int
    {
        if ([] === $config) {
            $config = [
                'textRecognition' => [
                    'htrId' => 38230,
                ],
            ];
        }

        $jsonBody = [
            'config' => $config,
            'image' => [
                'imageUrl' => $imageURL,
            ],
        ];

        $processId = 0;
        $content = $this->request('POST', self::PROCESSES_URL, $jsonBody);
        $parsedContent = (array) $content;
        if (!empty($parsedContent)) {
            if ($content->{'status'} === 'CREATED') {
                $processId = $content->{'processId'};
            }
        }

        return $processId;
    }

    /**
     * @param int $processId
     * @return string
     */
    public function retrieveProcessResult(int $processId): string
    {
        $url = self::PROCESSES_URL.'/'.$processId;

        $content = $this->request('GET', $url, []);
        $parsedContent = (array) $content;
        $textResult = '';

        if (!empty($parsedContent)) {
            if ($content->{'status'} === 'FINISHED') {
                $textContent = $content->{'content'};
                $textResult = $textContent->{'text'};
            }
        }

        return $textResult;
    }

    /**
     * @param int $statusCode
     * @return string
     */
    private function getErrorMessage(int $statusCode): string
    {
        $errorMessage = '';
        switch ($statusCode) {
            case 0:
                $errorMessage = 'Transkribus API returned null';
                break;
            case 401:
                $errorMessage = 'Error Code '.$statusCode.' :: Credentials are incorrect!';
                break;
            default:
                $errorMessage = 'Error Code '.$statusCode.' :: Error initiating upload process, try again!';
                break;
        }
        return $errorMessage;
    }

    /**
     * @param string $method
     * @param string $url
     * @param mixed[] $jsonBody
     * @return object
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

            if (is_null($content)) {
                $message = $this->getErrorMessage(0);
                throw new OcrException('transkribus-error', [$message]);
            }
            return $content;
        } else {
            $message = $this->getErrorMessage($statusCode);
            throw new OcrException('transkribus-error', [$message]);
        }
    }
}
