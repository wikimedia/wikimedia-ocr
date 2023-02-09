<?php
declare(strict_types = 1);

namespace App\Engine;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranskribusClient
{

    /** @var HttpClientInterface */
    private $httpClient;

    /** @var int transkribus process ID. */
    private $processId;

    /** @var string transkribus access token. */
    private $accessToken = '';

    /** @var string Error message. */
    private $errorMessage;

     /** @var bool response error status . */
    private $reponseHasError = false;
    
    /** @var string Transcribed result. */
    private $textResult = '';

    /** @var $langs valid languages. */
    private $langs = '';

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
     * @param array $config
     */
    public function initProcess($imageURL, $config): self
    {
        if ([] === $config) {
            $config = [
                'textRecognition' => [
                    'htrId' => 38230,
                ]
            ];
        }
        $response = $this->httpClient
        ->request(
            'POST',
            self::PROCESSES_URL,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
                'json' => [
                    'config' => $config,
                    'image' => [
                        'imageUrl' => $imageURL,
                    ],
                ],
            ]
        );

        $statusCode = $response->getStatusCode();
        if (200 === $statusCode) {
            $content = json_decode($response->getContent());
            
            if (is_null($content)) {
                $this->setErrorMessage(0);
                return $this;
            }
            
            if ($content->{'status'} === 'CREATED') {
                $this->processId = $content->{'processId'};
                $this->reponseHasError = false;
            }
        } else {
            $this->setErrorMessage($statusCode);
        }

        return $this;
    }

    public function retrieveProcessStatus(): self
    {
        $url = self::PROCESSES_URL.'/'.$this->processId;
        $response = $this->httpClient
        ->request(
            'GET',
            $url,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$this->accessToken,
                ],
            ]
        );
        
        $statusCode = $response->getStatusCode();
        if (200 === $statusCode) {
            $content = json_decode($response->getContent());
            if (is_null($content)) {
                $this->setErrorMessage(0);
                return $this;
            }

            if ($content->{'status'} === 'FINISHED') {
                $textContent = $content->{'content'};
                $this->textResult = $textContent->{'text'};
                $this->reponseHasError = false;
            }

        } else {
            $this->setErrorMessage($statusCode);
        }

        return $this;
    }
    
    private function setErrorMessage(int $statusCode): void
    {
        switch ($statusCode) {
            case 0:
                $this->errorMessage = 'Transkribus API returned null';
                $this->reponseHasError = true;
                break;
            case 401:
                $this->errorMessage = 'Error Code '.$statusCode.' :: Credentials are incorrect!';
                $this->reponseHasError = true;
                break;
            default:
                $this->errorMessage = 'Error Code '.$statusCode.' :: Error initiating upload process, try again!';
                $this->reponseHasError = true;
                break;
        }
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage;
    }

    public function getTextResult(): string
    {
        return $this->textResult;
    }

    public function hasError(): bool
    {
        return $this->reponseHasError;
    }

    public function setLanguages(array $langs): void
    {
        $this->langs = $langs;
    }
}
