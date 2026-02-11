<?php
declare( strict_types = 1 );

namespace App\Engine;

use App\Exception\OcrException;
use Krinkle\Intuition\Intuition;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use thiagoalessio\TesseractOCR\TesseractOCR;
use thiagoalessio\TesseractOCR\UnsuccessfulCommandException;

class TesseractEngine extends EngineBase {

	/** @var TesseractOCR */
	private $ocr;

	/** @var int Maximum value for page segmentation mode. */
	public const MAX_PSM = 13;

	/** @var int Default value for page segmentation mode. */
	public const DEFAULT_PSM = 3;

	/**
	 * TesseractEngine constructor.
	 * @param HttpClientInterface $httpClient
	 * @param Intuition $intuition
	 * @param string $projectDir
	 * @param TesseractOCR $tesseractOcr
	 */
	public function __construct(
		HttpClientInterface $httpClient,
		Intuition $intuition,
		string $projectDir,
		TesseractOCR $tesseractOcr
	) {
		parent::__construct( $intuition, $projectDir, $httpClient );
		$this->ocr = $tesseractOcr;
	}

	/**
	 * @inheritDoc
	 */
	public static function getId(): string {
		return 'tesseract';
	}

	/**
	 * @inheritDoc
	 */
	public function getResult(
		string $imageUrl,
		string $invalidLangsMode,
		array $crop,
		?array $langs = null,
		int $rotate = 0
	): EngineResult {
		// Check the URL and fetch the image data.
		$this->checkImageUrl( $imageUrl );

		[ $validLangs, $invalidLangs ] = $this->filterValidLangs( $langs, $invalidLangsMode );

		$image = $this->getImage( $imageUrl, $crop, self::DO_DOWNLOAD_IMAGE, $rotate );
		$this->ocr->imageData( $image->getData(), $image->getSize() );

		if ( $validLangs ) {
			$this->ocr->lang( ...$validLangs );
		}

		// Env vars are passed through by the thiagoalessio/tesseract_ocr package to the tesseract command,
		// but when they're loaded from Symfony's .env they aren't actually available (by design),
		// so we have to load this one manually. We only process one image at a time, so don't benefit from
		// multiple threads. See https://github.com/tesseract-ocr/tesseract/issues/898 for some more info.
		putenv( 'OMP_THREAD_LIMIT=1' );
		try {
			$text = $this->ocr->run();
		} catch ( UnsuccessfulCommandException $e ) {
			// An UnsuccessfulCommandException is thrown when there's no output, but that's not an
			// actual error so we check for it here and just show a warning. The same exception class
			// is also used for other things, hence the message check here.
			if ( strpos( $e->getMessage(), 'The command did not produce any output' ) !== false ) {
				return new EngineResult( '', [ $this->intuition->msg( 'tesseract-no-text-error' ) ] );
			}
			throw $e;
		}

		$warnings = $invalidLangs ? [ $this->getInvalidLangsWarning( $invalidLangs ) ] : [];
		return new EngineResult( $text, $warnings );
	}

	/**
	 * Set the page segmentation mode.
	 * @param int $psm
	 */
	public function setPsm( int $psm ): void {
		$this->validateOption( 'psm', $psm, self::MAX_PSM );
		$this->ocr->psm( $psm );
	}

	/**
	 * Get available PSM IDs and values.
	 * @return mixed[][]
	 */
	public function getAvailablePsms(): array {
		$psms = [];
		$psmIds = [ 0, 1, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13 ];
		foreach ( $psmIds as $psmId ) {
			array_push( $psms, [
				'value' => $psmId,
				// The following messages can be used here: 'tesseract-psm-0', 'tesseract-psm-1',
				// 'tesseract-psm-3', 'tesseract-psm-4', 'tesseract-psm-5', 'tesseract-psm-6', 'tesseract-psm-7',
				// 'tesseract-psm-8', 'tesseract-psm-9', 'tesseract-psm-10', 'tesseract-psm-11', 'tesseract-psm-12',
				// 'tesseract-psm-13'
				'label' => $this->intuition->msg( 'tesseract-psm-' . $psmId ),
			] );
		}
		return $psms;
	}

	/**
	 * Validates the given option.
	 * @param string $option
	 * @param int $given
	 * @param int $maximum
	 * @throws OcrException
	 */
	private function validateOption( string $option, int $given, int $maximum ): void {
		if ( $given > $maximum ) {
			throw new OcrException(
				'tesseract-param-error',
				[
					$this->intuition->msg( "tesseract-$option-label" ),
					$given,
					$maximum,
				]
			);
		}
	}
}
