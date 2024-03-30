<?php
declare( strict_types = 1 );

namespace App\Engine;

use App\Exception\OcrException;
use Google\Cloud\Vision\V1\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\ImageContext;
use Google\Cloud\Vision\V1\TextAnnotation;
use Krinkle\Intuition\Intuition;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleCloudVisionEngine extends EngineBase {
	/** @var string The API key. */
	protected $key;

	/** @var ImageAnnotatorClient */
	protected $imageAnnotator;

	/**
	 * GoogleCloudVisionEngine constructor.
	 * @param string $keyFile Filesystem path to the credentials JSON file.
	 * @param Intuition $intuition
	 * @param string $projectDir
	 * @param HttpClientInterface $httpClient
	 */
	public function __construct(
		string $keyFile,
		Intuition $intuition,
		string $projectDir,
		HttpClientInterface $httpClient
	) {
		parent::__construct( $intuition, $projectDir, $httpClient );
		if ( !empty( $keyFile ) ) {
			$this->imageAnnotator = new ImageAnnotatorClient( [ 'credentials' => $keyFile ] );
		}
	}

	/**
	 * @inheritDoc
	 */
	public static function getId(): string {
		return 'google';
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
		$this->checkImageUrl( $imageUrl );

		[ $validLangs, $invalidLangs ] = $this->filterValidLangs( $langs, $invalidLangsMode );

		$imageContext = new ImageContext();
		if ( $validLangs ) {
			$imageContext->setLanguageHints( $this->getLangCodes( $validLangs ) );
		}

		if ( !$this->imageAnnotator ) {
			throw new OcrException( 'google-error', [ 'Key for Google OCR engine is missing' ] );
		}

		$image = $this->getImage( $imageUrl, $crop );
		$imageUrlOrData = $image->hasData() ? $image->getData() : $image->getUrl();
		$response = $this->imageAnnotator->textDetection( $imageUrlOrData, [ 'imageContext' => $imageContext ] );

		// Re-try with direct upload if the error returned is something similar to
		// "The URL does not appear to be accessible by us. Please double check or download the content and pass it in."
		// There doesn't seem to be a specific error code for this (it is usually 3, but that's also used for other
		// things), so it seems like we have to check the actual message string.
		if ( $response->getError()
			&& stripos( $response->getError()->getMessage(), 'download the content and pass it in' ) !== false
		) {
			$image = $this->getImage( $imageUrl, $crop, self::DO_DOWNLOAD_IMAGE );
			$response = $this->imageAnnotator->textDetection( $image->getData(), [ 'imageContext' => $imageContext ] );
		}

		// Other errors, report to the user.
		if ( $response->getError() ) {
			throw new OcrException( 'google-error', [ $response->getError()->getMessage() ] );
		}

		$annotation = $response->getFullTextAnnotation();
		$resText = $annotation instanceof TextAnnotation ? $annotation->getText() : '';
		$warnings = $invalidLangs ? [ $this->getInvalidLangsWarning( $invalidLangs ) ] : [];
		return new EngineResult( $resText, $warnings );
	}
}
