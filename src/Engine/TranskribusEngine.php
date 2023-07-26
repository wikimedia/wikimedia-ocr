<?php
declare( strict_types = 1 );

namespace App\Engine;

use App\Exception\OcrException;
use Krinkle\Intuition\Intuition;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranskribusEngine extends EngineBase {

	/** @var TranskribusClient */
	protected $transkribusClient;

	/** @var int line detection model ID to be used by the Transkribus engine */
	protected $lineId;

	/** @var int Default value for line detection model ID to be used by Transkribus */
	public const DEFAULT_LINEID = 0;

	/**
	 * TranskribusEngine constructor.
	 * @param TranskribusClient $transkribusClient
	 * @param Intuition $intuition
	 * @param string $projectDir
	 * @param HttpClientInterface $httpClient
	 */
	public function __construct(
		TranskribusClient $transkribusClient,
		Intuition $intuition,
		string $projectDir,
		HttpClientInterface $httpClient
	) {
		parent::__construct( $intuition, $projectDir, $httpClient );

		$this->transkribusClient = $transkribusClient;
	}

	/**
	 * @inheritDoc
	 */
	public static function getId(): string {
		return 'transkribus';
	}

	/**
	 * Set the line detection model ID for the Transkribus engine
	 * @param int $lineId
	 * @return void
	 */
	public function setLineId( int $lineId ): void {
		$this->lineId = $lineId;
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

		$image = $this->getImage( $imageUrl, $crop );
		$imageUrl = $image->getUrl();

		$points = '';
		if ( $crop ) {
			$x = $crop['x'];
			$y = $crop['y'];
			$yPlusH = $crop['y'] + $crop['height'];
			$xPlusW = $crop['x'] + $crop['width'];
			$points = $x . ',' . $y . ' ' . $xPlusW . ',' .
					$y . ' ' . $xPlusW . ',' . $yPlusH . ' ' . $x . ',' . $yPlusH;
		}

		$htrModelId = 0;
		[ $validLangs, $invalidLangs ] = $this->filterValidLangs( $langs, $invalidLangsMode );
		if ( !$validLangs ) {
			throw new OcrException( 'transkribus-no-lang-error' );
		}
		$langCodes = $this->getLangCodes( $validLangs );
		if ( count( $langCodes ) > 1 ) {
			throw new OcrException( 'transkribus-multiple-lang-error' );
		}
		$htrModelId = (int)$langCodes[0]['htr'];

		$processId = $this->transkribusClient->initProcess( $imageUrl, $htrModelId, $this->lineId, $points );

		$resText = '';
		while ( $this->transkribusClient->processStatus !== 'FINISHED' ) {
			$resText = $this->transkribusClient->retrieveProcessResult( $processId );
			sleep( 2 );
		}

		$warnings = $invalidLangs ? [ $this->getInvalidLangsWarning( $invalidLangs ) ] : [];
		return new EngineResult( $resText, $warnings );
	}
}
