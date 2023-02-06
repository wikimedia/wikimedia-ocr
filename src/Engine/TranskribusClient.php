<?php
declare(strict_types = 1);

namespace App\Engine;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranskribusClient
{

    /** @var HttpClientInterface */
    private $httpClient;

    /** @var array transkribus username and password. */
    private $credentials;

    /** @var int transkribus process ID. */
    private $processId;

    /** @var string transkribus access token. */
    private $accessToken = '';

    /** @var string Error message. */
    private $errorMessage;

    private $hasError = false;
    
    /** @var string Transcribed result. */
    private $textResult = '';

    /** @var string Transcribed result. */
    private const AUTHENTICATION_URL = 'https://account.readcoop.eu/auth/realms/readcoop/protocol/openid-connect/token';

    /** @var string Transcribed result. */
    private const PROCESSES_URL = "https://transkribus.eu/processing/v1/processes";
    
    /**
     * TranskribusClient constructor.
     * @param HttpClientInterface $httpClient
     * @param array $credentials
     */
    public function __construct(
        HttpClientInterface $httpClient,
        array $credentials
    ) {
        $this->httpClient = $httpClient;
        $this->credentials = $credentials;
        $this->setAccessToken();
    }

     /**
     * @inheritDoc
     */
    private function setAccessToken()
    {
        if(file_exists('transkribus_credentials.json')){
            $data = file_get_contents('transkribus_credentials.json');
            $content = json_decode($data);
            $this->accessToken = $content->{'access_token'};
        }else{
           $this->retrieveAccessToken();
        }        
    }

    private function retrieveAccessToken(){
        $response = $this->httpClient
                    ->request(
                        'POST',
                        self::AUTHENTICATION_URL,
                        [
                            'headers' => [
                                'Content-Type' => 'application/x-www-form-urlencoded',
                            ],
                            'body' => [
                                'grant_type' => 'password',
                                'username' => $this->credentials['username'],
                                'password' => $this->credentials['password'],
                                'client_id' => 'processing-api-client',
                                'scope' => 'offline_access',
                            ],
                        ]
                    );
        $statusCode = $response->getStatusCode();
        if (200 === $statusCode) {
            $content = json_decode($response->getContent());
            if (is_null($content)) {
                $message = 'Transkribus API returned null';
                $this->setErrorMessage($message);
            } else {
                $this->accessToken = $content->{'access_token'};
                $this->hasError = false;
                file_put_contents('transkribus_credentials.json', $response->getContent());
                $this->refereshAccessToken();
            }
        } elseif (401 === $statusCode) {
            $message = 'Error Code '.$statusCode.' :: Credentials are incorrect!';
            $this->setErrorMessage($message);

        } else {
            $message = 'Error Code '.$statusCode.' :: Error generating access_token, try again!';
            $this->setErrorMessage($message);
        }
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
                $message = 'Transkribus API returned null';
                $rawResponse   = $response->getContent(false);
                $this->setErrorMessage($message);
            } else {
                if($content->{'status'} === 'CREATED'){
                    $this->processId = $content->{'processId'};
                    $this->hasError = false;
                }                
            }
        } elseif (401 === $statusCode) {
            $this->refereshAccessToken();
            if($this->getHasError()){
                $message = 'Error Code '.$statusCode.' :: Credentials are incorrect!';
                $this->setErrorMessage($message);
            }else{
                $this->initProcess($imageURL, $config);
            }
            

        } else {
            $rawResponse   = $response->getContent(false);
            $message = 'Error Code '.$statusCode.' :: Error initiating upload process, try again!';
            $this->setErrorMessage($message);
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
                $message = 'Transkribus API returned null';
                $this->setErrorMessage($message);
            } else {
                
                if($content->{'status'} === 'FINISHED'){
                    $textContent = $content->{'content'};
                    $this->textResult = $textContent->{'text'};
                    $this->hasError = false;
                }                
            }
        } elseif (401 === $statusCode) {
            $this->refereshAccessToken();
            if($this->getHasError()){
                $message = 'Error Code '.$statusCode.' :: Credentials are incorrect!';
                $this->setErrorMessage($message);
            }else{
                $this->retryProcessStatus();
            }

        } else {
            $rawResponse   = $response->getContent(false);
            $message = 'Error Code '.$statusCode.' :: Error retrieving process status, try again!';
            $this->setErrorMessage($message);
        }
    }

    public function retryProcessStatus(){
        $this->retrieveProcessStatus();
    }

    public function refereshAccessToken(){
        if(!file_exists('transkribus_credentials.json')){
            $this->setAccessToken();
            
        }else{
            $data = file_get_contents('transkribus_credentials.json');
            $content = json_decode($data);
            $refreshToken = $content->{'refresh_token'};
            $response = $this->httpClient
            ->request(
                'POST',
                self::AUTHENTICATION_URL,
                [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ],
                    'body' => [
                        'grant_type' => 'refresh_token',
                        'username' => $this->credentials['username'],
                        'client_id' => 'processing-api-client',
                        'refresh_token' => $refreshToken,
                    ],
                ]
            );
            $statusCode = $response->getStatusCode();
            if (200 === $statusCode) {
                $content = json_decode($response->getContent());

                if (is_null($content)) {
                    $message = 'Transkribus API returned null';
                } else {
                    $this->accessToken = $content->{'access_token'};
                    $this->hasError = false;
                    file_put_contents('transkribus_credentials.json', $response->getContent());
                }
            } elseif (401 === $statusCode) {
                $message = 'Error Code '.$statusCode.' :: Credentials are incorrect!';
                $this->setErrorMessage($message);
            
            } else {
                $rawResponse   = $response->getContent(false);
                $message = 'Error Code '.$statusCode.' :: Error refreshing access_token, try again!';
                $this->setErrorMessage($message);
            }

        }
        
        
    }

    private function setErrorMessage($message){
        $this->errorMessage = $message;
        $this->hasError = true;
    }

    public function getErrorMessage(){
        return $this->errorMessage;
    }

    public function getTextResult(){
        return $this->textResult;
    }

    public function getHasError(){
        return $this->hasError;
    }

    
    
}
