<?php
declare( strict_types = 1 );

namespace App\Engine;

use App\Exception\OcrException;
use stdclass;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class TranskribusClient {

	/** @var HttpClientInterface */
	private $httpClient;

	/** @var string Transkribus access token. */
	private $accessToken;

	/** @var string Transkribus request status. */
	public $processStatus;

	/** @var string Transkribus refresh token. */
	private $refreshToken;

	/** @var int Transkribus no content status code. */
	private const ERROR_NO_CONTENT = 0;

	/** @var string Transkribus process URL. */
	private const PROCESSES_URL = "https://transkribus.eu/processing/v1/processes";

	/** @var string Transkribus authentication URL. */
	private const AUTH_URL = "https://account.readcoop.eu/auth/realms/readcoop/protocol/openid-connect/token";

	/**
	 * TranskribusClient constructor.
	 * @param string $accessToken
	 * @param string $refreshToken
	 * @param HttpClientInterface $httpClient
	 */
	public function __construct( string $accessToken, string $refreshToken, HttpClientInterface $httpClient ) {
		$this->accessToken = $accessToken;
		$this->refreshToken = $refreshToken;
		$this->httpClient = $httpClient;
	}

	/**
	 * @param string $imageURL
	 * @param int $htrId
	 * @param int $lineId
	 * @param string $points
	 * @return int
	 */
	public function initProcess( string $imageURL, int $htrId, int $lineId, string $points ): int {
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

		// add line detection model to the config, if available
		if ( $lineId !== 0 ) {
			$lineDetection = [ 'modelId' => $lineId ];
			$jsonBody['config']['lineDetection'] = $lineDetection;
		}

		if ( !empty( $points ) ) {
			$cropContent = [
				'regions' => [ [
					'id' => 'region_1',
					'coords' => [
						'points' => $points,
					],
				] ],
			];
			$jsonBody['content'] = $cropContent;
		}

		$content = $this->request( 'POST', self::PROCESSES_URL, $jsonBody );

		if ( $content->status !== 'FAILED' ) {
			$processId = $content->processId;
			return $processId;
		}

		throw new OcrException( 'transkribus-init-process-error' );
	}

	/**
	 * @param int $processId
	 * @return string
	 */
	public function retrieveProcessResult( int $processId ): string {
		$url = self::PROCESSES_URL . '/' . $processId;

		$content = $this->request( 'GET', $url );
		$textResult = '';

		if ( $content->status === 'FAILED' ) {
			throw new OcrException( 'transkribus-failed-process-error' );
		}

		if ( $content->status === 'FINISHED' ) {
			$this->processStatus = $content->status;
			$textResult = $content->content->text ?? '';
		}

		return $textResult;
	}

	/**
	 * @param int $statusCode
	 */
	private function throwException( int $statusCode ): void {
		switch ( $statusCode ) {
			case self::ERROR_NO_CONTENT:
				throw new OcrException(
					'transkribus-empty-response-error'
				);
			case 401:
				throw new OcrException(
					'transkribus-unauthorized-error',
					[ $statusCode ]
				);
			default:
				throw new OcrException(
					'transkribus-default-error',
					[ $statusCode ]
				);
		}
	}

	/**
	 * @param string $method
	 * @param string $url
	 * @param mixed[] $jsonBody
	 * @return stdClass
	 */
	private function request(
		string $method,
		string $url,
		array $jsonBody = []
	): object {
		$body = [
			'headers' => [
				'Content-Type' => 'application/json',
				'Authorization' => 'Bearer ' . $this->accessToken,
			],
		];
		if ( $jsonBody != [] ) {
			$body['json'] = $jsonBody;
		}

		$response = $this->httpClient->request( $method, $url, $body );
		$statusCode = $response->getStatusCode();
		if ( $statusCode === 200 ) {
			$content = json_decode( $response->getContent() );

			if ( !empty( $content ) ) {
				return $content;
			}
			$this->throwException( self::ERROR_NO_CONTENT );
		}

		if ( $statusCode === 401 ) {
			$this->setAccessToken();

			$body['headers']['Authorization'] = 'Bearer ' . $this->accessToken;
			$response = $this->httpClient->request( $method, $url, $body );
			$statusCode = $response->getStatusCode();

			if ( $statusCode === 200 ) {
				$content = json_decode( $response->getContent() );

				if ( !empty( $content ) ) {
					return $content;
				}
				$this->throwException( self::ERROR_NO_CONTENT );
			}
		}
		$this->throwException( $statusCode );
	}

	private function setAccessToken(): void {
		$response = $this->getRefreshTokenResponse( $this->refreshToken );
		$statusCode = $response->getStatusCode();
		if ( $statusCode !== 200 ) {
			$this->throwException( $statusCode );
		}
		$content = json_decode( $response->getContent() );
		if ( empty( $content ) ) {
			$this->throwException( self::ERROR_NO_CONTENT );
		}
		$this->accessToken = $content->{'access_token'};
	}

	/**
	 * @param string $token
	 * @return ResponseInterface
	 */
	public function getRefreshTokenResponse( string $token ): ResponseInterface {
		$body = [
			'grant_type' => 'refresh_token',
			'client_id' => 'processing-api-client',
			'refresh_token' => $token,
		];

		$response = $this->authRequest( $body );
		return $response;
	}

	/**
	 * @param string $userName
	 * @param string $password
	 * @return ResponseInterface
	 */
	public function getAccessTokenResponse( string $userName, string $password ): ResponseInterface {
		$body = [
			'grant_type' => 'password',
			'username' => $userName,
			'password' => $password,
			'client_id' => 'processing-api-client',
			'scope' => 'offline_access',
		];

		$response = $this->authRequest( $body );
		return $response;
	}

	/**
	 * @param mixed[] $body
	 * @return ResponseInterface
	 */
	private function authRequest( array $body ): ResponseInterface {
		$response = $this->httpClient->request(
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
