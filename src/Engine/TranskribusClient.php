<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranskribusClient
{

    /** @var HttpClientInterface */
    private $httpClient;

    /** @var string Transkribus access token. */
    private $accessToken;

    /** @var string Transkribus request status. */
    public $processStatus;

    /** @var string Transkribus refresh token. */
    private $refreshToken;

    /** @var string Transkribus process URL. */
    private const PROCESSES_URL = "https://transkribus.eu/processing/v1/processes";

    /** @var string Transkribus authentication URL. */
    private const AUTH_URL = "https://account.readcoop.eu/auth/realms/readcoop/protocol/openid-connect/token";

    /**
     * TranskribusClient constructor.
     * @param HttpClientInterface $httpClient
     * @param string $accessToken
     * @param string $refreshToken
     */
    public function __construct(
        HttpClientInterface $httpClient,
        string $accessToken,
        string $refreshToken
    ) {
        $this->httpClient = $httpClient;
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
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
        }

        if (401 === $statusCode) {
            $this->setAccessToken();

            $body['headers']['Authorization'] = 'Bearer '.$this->accessToken;
            $response = $this->httpClient->request($method, $url, $body);
            $statusCode = $response->getStatusCode();

            if (200 === $statusCode) {
                $content = json_decode($response->getContent());

                if (!empty($content)) {
                    return $content;
                }
                $this->throwException(0);
            }
        }
        $this->throwException($statusCode);
    }

    private function setAccessToken(): void
    {
        $response = self::getRefreshTokenResponse($this->refreshToken, $this->httpClient);
        $statusCode = $response->getStatusCode();
        if (200 === $statusCode) {
            $content = json_decode($response->getContent());

            if (!empty($content)) {
                $this->accessToken = $content->{'access_token'};
            }
            $this->throwException(0);
        }
        $this->throwException($statusCode);
    }

    /**
     * @param string $token
     * @param HttpClientInterface $client
     */
    public static function getRefreshTokenResponse(string $token, HttpClientInterface $client): object
    {
        $body = [
            'grant_type' => 'refresh_token',
            'client_id' => 'processing-api-client',
            'refresh_token' => $token,
        ];

        $response = self::authRequest($body, $client);
        return $response;
    }

    /**
     * @param string $userName
     * @param string $password
     * @param HttpClientInterface $client
     */
    public static function getAccessTokenResponse(
        string $userName,
        string $password,
        HttpClientInterface $client
    ): object {
        $body = [
            'grant_type' => 'password',
            'username' => $userName,
            'password' => $password,
            'client_id' => 'processing-api-client',
            'scope' => 'offline_access',
        ];

        $response = self::authRequest($body, $client);

        return $response;

    }

    /**
     * @param mixed[] $body
     * @param HttpClientInterface $client
     */
    private static function authRequest(array $body, HttpClientInterface $client): object
    {
        $response = $client->request(
            'POST',
            self::AUTH_URL,
            [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => $body,
            ]
        );
        return $response;
    }
}
