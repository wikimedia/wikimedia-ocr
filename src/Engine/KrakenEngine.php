<?php
// phpcs:disable MediaWiki.Usage.ForbiddenFunctions.popen

declare( strict_types = 1 );

namespace App\Engine;

use Krinkle\Intuition\Intuition;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class KrakenEngine extends EngineBase {

	/** @var string segmentation model to be used by the kraken engine */
	protected $segmentationModel;

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
		if ( $validLangs ) {
			# var_dump($validLangs);
			# var_dump($this->getLangCodes($validLangs));
			# var_dump(...$this->getLangCodes($validLangs));
			# $model = implode( ',',$this->getLangCodes( $validLangs ) );
			# kraken does not support more than one model, so use the first one.
			$ocrModel = $this->getLangCodes( $validLangs )[0];
		} else {
			$ocrModel = 'german_print';
		}

		if ( $crop ) {
			$box = ' ' . $crop['width'] . 'x' . $crop['height'] . '+' . $crop['x'] . '+' . $crop['y'];
		} else {
			$box = '';
		}

		$command = $this->projectDir . '/bin/kraken_ocr ' . $imageUrl . ' ' .
			$ocrModel . ' ' . $this->segmentationModel . $box;

		$handle = popen( $command, 'rb' );
		$text = stream_get_contents( $handle );
		pclose( $handle );

		$warnings = $invalidLangs ? [ $this->getInvalidLangsWarning( $invalidLangs ) ] : [];
		return new EngineResult( $text, $warnings );
	}

	/**
	 * Get segmentation models accepted by the engine.
	 * @return string[] Names of segmentation models.
	 */
	public function getValidSegmentationModels(): array {
		# TODO: get models from filesystem.
		$models = [ 'default', 'ubma_segmentation' ];
		return $models;
	}

	/**
	 * Set the segmentation model for the kraken engine
	 * @param string $segmentationModel
	 * @return void
	 */
	public function setSegmentationModel( string $segmentationModel ): void {
		$this->segmentationModel = $segmentationModel;
	}
}
