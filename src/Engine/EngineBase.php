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
		?array $models = null,
		int $rotate = 0
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
		return $this->intuition->getLangName( $model ) ?: '';
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
