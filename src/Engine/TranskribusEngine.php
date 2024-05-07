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

	/** @var string[] Model names for corresponding line detection model lang codes */
	public const LINE_ID_MODEL_NAMES = [
		'bali' => 'Balinese Line Detection Model',
	];

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
	 * Get line detection models accepted by the engine
	 * @param bool $onlyLineIds Whether to return only the line detection model IDs
	 * @param bool $onlyLineIdLangs Whether to return only the line detection model IDs lang codes
	 * @return string[] Line detection model lang codes or model IDs or model ID names
	 */
	public function getValidLineIds( bool $onlyLineIds = false, bool $onlyLineIdLangs = false ): array {
		$langs = $this->getLangList()[ static::getId() ];
		$filteredLangList = array_filter(
			$langs, static function ( $value ) {
				return $value[ 'line' ] !== "";
			}
		);

		$lineIdLangs = array_keys( $filteredLangList );

		// return only the lang names as written in the models.json file
		if ( $onlyLineIdLangs ) {
			return $lineIdLangs;
		}

		// create a list that maps from lang name to line detection model name
		$lineIDList = [];
		foreach ( $lineIdLangs as $lineIdLang ) {
			$lineIDList[$lineIdLang] = $this->getLineIdModelName( $lineIdLang );
		}

		// create a list that maps from line detection model ID to line detection model name
		$list = [];
		foreach ( $lineIdLangs as $lineIDKey ) {
			$list[ $filteredLangList[ $lineIDKey ][ 'line' ] ] = $lineIDList[ $lineIDKey ];
		}

		// return only the line detection model IDs
		if ( $onlyLineIds ) {
			return array_keys( $list );
		}

		return $list;
	}

	/**
	 * Get name of the given line detection model from the language code
	 * @param string|null $lineIdLang
	 * @return string
	 */
	public function getLineIdModelName( ?string $lineIdLang = null ): string {
		return self::LINE_ID_MODEL_NAMES[$lineIdLang];
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
