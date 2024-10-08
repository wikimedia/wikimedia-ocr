<?php
declare( strict_types = 1 );

namespace App\Engine;

use App\Exception\OcrException;
use Imagine\Gd\Imagine;
use Krinkle\Intuition\Intuition;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class EngineBase {

	public const ALLOWED_FORMATS = [ 'png', 'jpeg', 'jpg', 'gif', 'tiff', 'tif', 'webp' ];

	public const WARN_ON_INVALID_LANGS = 'warn';
	public const ERROR_ON_INVALID_LANGS = 'error';

	/** @const Download the image data to the web server. */
	public const DO_DOWNLOAD_IMAGE = true;

	/** @var string[] The host names for the images. */
	protected $imageHosts = [];

	/** @var Intuition */
	protected $intuition;

	/** @var string */
	protected $projectDir;

	/** @var HttpClientInterface */
	private $httpClient;

	/** @var string[][] Local PHP array copy of models.json */
	protected $modelList;

	/** @var string[] Additional localized names for non-standard language codes. */
	public const LANG_NAMES = [
		'Fraktur' => 'Fraktur script',
		'Latin' => 'Latin script',
		'az-cyrl' => 'Azərbaycan (qədim yazı)',
		'bali' => 'Balinese palm-leaf manuscripts 16th century',
		'ben-print' => 'Bengali Printed Books +150 New',
		'cs-space' => 'Old Czech Handwriting (with spaces)',
		'cs-no-space' => 'Old Czech Handwriting (without spaces)',
		'da-goth' => '19th century Danish Gothic handwriting v.1.1',
		'da-goth-print' => 'Danish gothic print 1859-1888 v4',
		'da-gjen' => 'Gjentofte 1881-1913 Denmark',
		'de-frk' => 'Deutsch (Fraktur)',
		'de-17' => 'Dutch_XVII_Century',
		'de-hd-m1' => 'Transkribus Dutch Handwriting M1',
		'dev' => 'Devanagari Mixed M1A',
		'el-ligo' => 'Ligorio 0.3 PyL',
		'el-print' => 'Noscemus GM 6',
		'en-b2022' => 'Transkribus B2022 English Model M4',
		'en-handwritten-m3' => 'Transkribus English Handwriting M3',
		'en-print-m1' => 'Transkribus Print M1',
		'en-typewriter' => 'Transkribus Typewriter',
		'enm' => 'Middle English (1100-1500)',
		'es-md' => 'Diario de Madrid 1788-1825',
		'es-old' => 'español (viejo)',
		'es-redonda-extended-v1_2' => 'SpanishRedonda_sXVI-XVII_extended_v1.2',
		'et-court' => 'Estonian Court Records 19thC',
		'fin' => 'NLF_Newseye_GT_FI_M2+',
		'fr-m1' => 'Transkribus French Model 1',
		'frm' => 'moyen français (1400-1600)',
		'fro' => 'Franceis, François, Romanz (1400-1600)',
		'ger-hd-m1' => 'Transkribus German handwriting M1',
		'ger-15' => '15th-16th century German',
		'he-dijest' => 'Hebrew DiJeSt 2.0',
		'hu-hand-19' => 'Hungarian handwriting 19th–20th cent.',
		'it-old' => 'italiano antico',
		'it-hd-m1' => 'Transkribus Italian Handwriting M1',
		'jv-01' => 'Javanese model v0.1 b06/24',
		'ka-old' => 'ქართული (ძველი)',
		'ko-vert' => '한국어 (세로)',
		'kur' => 'کوردی',
		'la-caro' => 'Carolingian Minuscule Model CMM 9th-11th c.',
		'la-in' => 'Latin Incunabula (Reichenau)',
		'la-med' => 'UCL–University of Toronto #7',
		'la-neo' => 'Pylaia_NeoLatin_Ravenstein',
		'nl-1605' => 'Admiraliteit Zeeland 1605-1609 compleet',
		'nl-mount' => 'Dutch Mountains (18th Century)',
		'nl-news' => 'Dutch newspapers 17th century',
		'no-1820' => 'NorHand 1820-1940',
		'no-1874' => 'Sunnhordland Partition Protocols ',
		'osd' => 'Orientation and script detection module',
		'pl-m2' => 'Transkribus Polish M2',
		'pt-m1' => 'General Portuguese M1',
		'pt-17' => 'SPJCL17C V4.2',
		'pt-hd' => 'Portuguese Handwriting 16th-19th century',
		'ro-print' => 'RTA2 (Romanian Transition Alphabet)',
		'rus-hd-2' => 'Russian generic handwriting 2',
		'rus-print' => 'Russian print of the 18th century',
		'ru-petr1708' => 'Русский (старая орфография)',
		'san' => 'Devanagari Mixed M1A',
		'sl-hand-18' => 'Slovenian 18th century manuscript',
		'sk-hand' => 'Handwritten Glagolitic',
		'sr-latn' => 'Српски (латиница)',
		'swe-3' => 'Stockholm Notaries 1700 3.0',
		'swe-lion-i' => 'The Swedish Lion I',
		'syr' => 'leššānā Suryāyā',
		'uz-cyrl' => 'oʻzbekcha',
		'uk-20th-print' => 'Printed Ukrainian 20th century',
		'uk-generic-handwriting-1' => 'Ukrainian generic handwriting 1',
		'uk-wikisource-print' => 'Ukrainian Wikisource Print',
		'yi-hd' => 'The Dybbuk for Yiddish Handwriting'
	];

	/**
	 * EngineBase constructor.
	 * @param Intuition $intuition
	 * @param string $projectDir
	 * @param HttpClientInterface $httpClient
	 */
	public function __construct( Intuition $intuition, string $projectDir, HttpClientInterface $httpClient ) {
		$this->intuition = $intuition;
		$this->projectDir = $projectDir;
		$this->httpClient = $httpClient;
	}

	/**
	 * Unique identifier for the engine.
	 * @return string
	 */
	abstract public static function getId(): string;

	/**
	 * Get transcribed text from the given image.
	 * @param string $imageUrl
	 * @param string $invalidLangsMode
	 * @param int[] $crop
	 * @param string[]|null $models
	 * @return EngineResult
	 */
	abstract public function getResult(
		string $imageUrl,
		string $invalidLangsMode,
		array $crop,
		?array $models = null
	): EngineResult;

	/**
	 * Get the model list for this engine (from models.json).
	 * @return mixed[][]
	 */
	public function getModelList(): array {
		if ( !$this->modelList ) {
			$models = json_decode( file_get_contents( $this->projectDir . '/public/models.json' ), true );
			if ( $models ) {
				$this->modelList = $models[ static::getId() ];
			}
		}

		return $this->modelList;
	}

	/**
	 * Get names of models accepted by the engine.
	 * @param bool $withNames Whether to include the model title (if available).
	 * @return string[] Model codes, optionally as keys with model titles as the values.
	 */
	public function getValidModels( bool $withNames = false ): array {
		$models = $this->getModelList();
		if ( !$withNames ) {
			return array_keys( $models );
		}

		// Add the titles for each model.
		$list = [];
		foreach ( $models as $modelId => $modelDetails ) {
			$list[$modelId] = $this->getModelTitle( $modelId );
		}

		return $list;
	}

	/**
	 * Get the title of the given model, falling back to a language name if the
	 * model ID happens to match a language code.
	 * @param string|null $model
	 * @return string
	 */
	public function getModelTitle( ?string $model = null ): string {
		if ( isset( $this->getModelList()[ $model ]['title'] ) ) {
			return $this->getModelList()[ $model ]['title'];
		}
		if ( isset( static::LANG_NAMES[$model] ) ) {
			return static::LANG_NAMES[$model];
		}
		return $this->intuition->getLangName( $model ) ?: '';
	}

	/**
	 * Transform the given ISO 639-1 codes into the language codes needed by this type of Engine.
	 * @param string[] $langs
	 * @return mixed[]
	 */
	public function getLangCodes( array $langs ): array {
		return array_map( function ( $lang ) {
			$language = $this->getModelList()[ $lang ];
			return isset( $language ) ? $lang : '';
		}, $langs );
	}

	/**
	 * Set the allowed image hosts.
	 * @param string $imageHosts
	 */
	public function setImageHosts( string $imageHosts ): void {
		$this->imageHosts = array_map( 'trim', explode( ',', $imageHosts ) );
	}

	/**
	 * Get the allowed image hosts.
	 * @return string[]
	 */
	public function getImageHosts(): array {
		return $this->imageHosts;
	}

	/**
	 * Checks that the given image URL is valid.
	 * @param string $imageUrl
	 * @throws OcrException
	 */
	public function checkImageUrl( string $imageUrl ): void {
		$hostRegex = implode( '|', array_map( 'preg_quote', $this->getImageHosts() ) );
		$formatRegex = implode( '|', self::ALLOWED_FORMATS );
		$regex = "/^https?:\/\/($hostRegex)\/.+($formatRegex)$/";
		$matches = preg_match( $regex, strtolower( $imageUrl ) );
		if ( $matches !== 1 ) {
			$params = [ count( $this->getImageHosts() ), $this->intuition->listToText( $this->getImageHosts() ) ];
			throw new OcrException( 'image-url-error', $params );
		}
	}

	/**
	 * @param string[]|null $langs
	 * @param string $invalidLangsMode
	 * @return string[][] [ valid languages in $langs, invalid languages in $langs ]
	 * @throws OcrException If there are invalid languages and $invalidLangsMode is self::ERROR_ON_INVALID_LANGS
	 */
	public function filterValidLangs( ?array $langs, string $invalidLangsMode ): array {
		$invalidLangs = array_values( array_diff( $langs, $this->getValidModels() ) );
		if ( !$invalidLangs ) {
			return [ $langs, [] ];
		}

		if ( self::WARN_ON_INVALID_LANGS === $invalidLangsMode ) {
			return [ array_values( array_diff( $langs, $invalidLangs ) ), $invalidLangs ];
		}

		throw new OcrException( 'langs-param-error', [
			count( $invalidLangs ),
			$this->intuition->listToText( $invalidLangs ),
		] );
	}

	/**
	 * @param string[] $invalidLangs
	 * @return string
	 */
	protected function getInvalidLangsWarning( array $invalidLangs ): string {
		return $this->intuition->msg(
			'engine-invalid-langs-warning',
			[ 'variables' => [ $this->intuition->listToText( $invalidLangs ) ] ]
		);
	}

	/**
	 * @param string $imageUrl The original image URL.
	 * @param int[] $crop Array with keys `x, `y`, `width` and `height`.
	 * @param ?bool $downloadMode Whether to download the image or not.
	 * @return Image
	 * @throws OcrException If the image couldn't be fetched.
	 */
	public function getImage( string $imageUrl, array $crop, ?bool $downloadMode = false ): Image {
		$image = new Image( $imageUrl, $crop );

		if ( self::DO_DOWNLOAD_IMAGE !== $downloadMode && !$image->needsCropping() ) {
			return $image;
		}

		$imageResponse = $this->httpClient->request( 'GET', $image->getUrl(), [ 'timeout' => 120 ] );
		try {
			$data = $imageResponse->getContent();
		} catch ( ClientException $exception ) {
			throw new OcrException( 'image-retrieval-failed', [ $exception->getMessage() ] );
		}

		if ( !$image->needsCropping() ) {
			// If it doesn't need cropping, use the full image's data.
			$image->setData( $data );
			$image->setSize( (int)$imageResponse->getHeaders()['content-length'][0] );
		} else {
			// Otherwise, crop it.
			$imagine = new Imagine();
			$loadedImage = $imagine->load( $data );
			$croppedImage = $image->getCrop()->apply( $loadedImage );
			$image->setData( $croppedImage->get( 'jpg' ) );
		}

		return $image;
	}
}
