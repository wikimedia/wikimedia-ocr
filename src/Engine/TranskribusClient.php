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
    
    /** @var $config. */
    private $config = [];

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
     */
    public function initProcess(string $imageURL): self
    {
        if ([] === $this->config) {
            $this->config = [
                'textRecognition' => [
                    'htrId' => 38230,
                ],
            ];
        }

        $jsonBody = [
            'config' => $this->config,
            'image' => [
                'imageUrl' => $imageURL,
            ],
        ];

        $content = $this->request('POST', self::PROCESSES_URL, $jsonBody);
        $parsedContent = (array) $content;
        if (!empty($parsedContent)) {
            if ($content->{'status'} === 'CREATED') {
                $this->processId = $content->{'processId'};
                $this->reponseHasError = false;
            }
        }

        return $this;
    }

    public function retrieveProcessStatus(): self
    {
        $url = self::PROCESSES_URL.'/'.$this->processId;

        $content = $this->request('GET', $url, []);
        $parsedContent = (array) $content;

        if (!empty($parsedContent)) {
            if ($content->{'status'} === 'FINISHED') {
                $textContent = $content->{'content'};
                $this->textResult = $textContent->{'text'};
                $this->reponseHasError = false;
            }
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

     /**
     * @param string[] $langs
     */
    public function setLanguages(array $langs): void
    {
        $this->langs = $langs;
    }

     /**
     * @param string $method
     * @param string $url
     * @param array<string,mixed> $jsonBody
     * @return object
     */
    public function request(
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
                $this->setErrorMessage(0);
                return (object)[];
            }

            return $content;
        } else {
            $this->setErrorMessage($statusCode);
            return (object)[];
        }
    }
}
