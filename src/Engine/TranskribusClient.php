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

    public function initProcess($imageURL, $config = []){
        $response = $this->httpClient
        ->request(
            'POST',
            self::PROCESSES_URL,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$this->accessToken
                ],
                'json' => [
                    'config' => [
                        'textRecognition' => [
                            'htrId' => 38230
                        ]
                    ],
                    'image' => [
                        'imageUrl' => $imageURL
                    ]
                ],
            ]
        );

        $statusCode = $response->getStatusCode();
        if (200 === $statusCode) {
            $content = json_decode($response->getContent());
            
            if (is_null($content)) {
                $this->setErrorMessage('00');
                return $this;
            } 
            
            if($content->{'status'} === 'CREATED'){
                $this->processId = $content->{'processId'};
                $this->reponseHasError = false;
            } 

        } else {
            $this->setErrorMessage($statusCode);
        }

        return $this;
    }

    public function retrieveProcessStatus(){
        $url = self::PROCESSES_URL . '/'. $this->processId;
        $response = $this->httpClient
        ->request(
            'GET',
            $url,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer '.$this->accessToken
                ],
            ]
        );
        
        $statusCode = $response->getStatusCode();
        if (200 === $statusCode) {
            $content = json_decode($response->getContent());
            if (is_null($content)) {
                $this->setErrorMessage('00');
                return $this;
            } 

            if($content->{'status'} === 'FINISHED'){
                $textContent = $content->{'content'};
                $this->textResult = $textContent->{'text'};
                $this->reponseHasError = false;
            } 

        } else {
            $this->setErrorMessage($statusCode);
        }

        return $this;
    }
    
    private function setErrorMessage($statusCode){
        switch($statusCode){
            case '00':
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

    public function getErrorMessage(){
        return $this->errorMessage;
    }

    public function getTextResult(){
        return $this->textResult;
    }

    public function hasError(){
        return $this->reponseHasError;
    }

    
    
}
