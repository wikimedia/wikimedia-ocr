<?php
declare( strict_types = 1 );

namespace App\Engine;

use App\Exception\OcrException;
use Krinkle\Intuition\Intuition;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class KrakenEngine extends EngineBase {

	/**
	 * KrakenEngine constructor.
	 * @param Intuition $intuition
	 * @param string $projectDir
	 * @param HttpClientInterface $httpClient
	 */
	public function __construct( Intuition $intuition, string $projectDir, HttpClientInterface $httpClient ) {
		parent::__construct( $intuition, $projectDir, $httpClient );
	}

	/**
	 * @inheritDoc
	 */
	public static function getId(): string {
		return 'kraken';
	}

	/**
	 * @inheritDoc
	 */
	public function getResult(
		string $imageUrl,
		string $invalidLangsMode,
		array $crop,
		?array $langs = null
	): EngineResult {
		// Check the URL and fetch the image data.
		$this->checkImageUrl( $imageUrl );

		[ $validLangs, $invalidLangs ] = $this->filterValidLangs( $langs, $invalidLangsMode );

		$image = $this->getImage( $imageUrl, $crop, self::DO_DOWNLOAD_IMAGE );
		$this->ocr->imageData( $image->getData(), $image->getSize() );

		if ( $validLangs ) {
			$this->ocr->lang( ...$this->getLangCodes( $validLangs ) );
		}

		// Env vars are passed through to the kraken command,
		// but when they are loaded from Symfony's .env they aren't actually available (by design),
		// so we have to load this one manually. We only process one image at a time, so don't benefit from
		// multiple threads.
		putenv( 'OMP_THREAD_LIMIT=1' );
		$text = $this->ocr->run();

		$warnings = $invalidLangs ? [ $this->getInvalidLangsWarning( $invalidLangs ) ] : [];
		return new EngineResult( $text, $warnings );
	}
}
