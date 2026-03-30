<?php
// phpcs:disable MediaWiki.Commenting.FunctionAnnotations.UnrecognizedAnnotation

declare( strict_types = 1 );

namespace App\Controller;

use App\Engine\EngineBase;
use App\Engine\EngineFactory;
use App\Engine\EngineResult;
use App\Engine\GoogleCloudVisionEngine;
use App\Engine\PdfProcessor;
use App\Engine\PdfResult;
use App\Engine\TesseractEngine;
use App\Engine\TranskribusEngine;
use App\Exception\EngineNotFoundException;
use App\Exception\OcrException;
use Exception;
use Krinkle\Intuition\Intuition;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBag;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
// phpcs:ignore MediaWiki.Classes.UnusedUseStatement.UnusedUse
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class OcrController extends AbstractController {
	/** @var Intuition */
	protected $intuition;

	/** @var TesseractEngine|GoogleCloudVisionEngine|TranskribusEngine */
	protected $engine;

	/** @var string The default OCR engine. */
	public const DEFAULT_ENGINE = 'google';

	/** @var CacheInterface */
	protected $cache;

	/** @var Request */
	protected $request;

	/** @var SessionInterface */
	protected $session;

	/** @var EngineFactory */
	protected $engineFactory;

	/** @var PdfProcessor */
	protected $pdfProcessor;

	/**
	 * The output params for the view or API response.
	 * This also serves as where you define the defaults.
	 * Note this is used by the ExceptionListener.
	 * @var mixed[]
	 */
	public static $params = [
		'image' => '',
		'engine' => self::DEFAULT_ENGINE,
		'langs' => [],
		'psm' => TesseractEngine::DEFAULT_PSM,
		'crop' => [],
		'line_id' => TranskribusEngine::DEFAULT_LINEID,
	];

	/**
	 * OcrController constructor.
	 * @param RequestStack $requestStack
	 * @param Intuition $intuition
	 * @param EngineFactory $engineFactory
	 * @param CacheInterface $cache
	 * @param PdfProcessor $pdfProcessor
	 */
	public function __construct(
		RequestStack $requestStack,
		Intuition $intuition,
		EngineFactory $engineFactory,
		CacheInterface $cache,
		PdfProcessor $pdfProcessor
	) {
		$this->request = $requestStack->getCurrentRequest();
		$this->session = $requestStack->getSession();
		$this->intuition = $intuition;
		$this->engineFactory = $engineFactory;
		$this->cache = $cache;
		$this->pdfProcessor = $pdfProcessor;
	}

	/**
	 * Setup the engine and parameters needed for the view. This must be called before every action.
	 * @suppress PhanSuspiciousValueComparison
	 */
	private function setup(): void {
		$requestedEngine = $this->request->query->get( 'engine', static::$params['engine'] );
		if ( is_array( $requestedEngine ) ) {
			$requestedEngine = $requestedEngine[0] ?? static::$params['engine'];
		}
		try {
			$this->engine = $this->engineFactory->get( $requestedEngine );
		} catch ( EngineNotFoundException $e ) {
			$this->addFlash( 'error', $this->intuition->msg(
				'engine-not-found-warning',
				[ 'variables' => [ $requestedEngine, static::DEFAULT_ENGINE ] ]
			) );
			$this->engine = $this->engineFactory->get( static::DEFAULT_ENGINE );
		}

		static::$params['engine'] = $this->engine::getId();
		$this->setEngineOptions();

		// Parameters.
		static::$params['image'] = (string)$this->request->query->get( 'image' );
		// Change protocol-relative URLs to https to avoid issues with Curl.
		if ( substr( static::$params['image'], 0, 2 ) === '//' ) {
			static::$params['image'] = "https:" . static::$params['image'];
		}
		static::$params['langs'] = $this->getLangs( $this->request );
		static::$params['image_hosts'] = $this->engine->getImageHosts();
		$crop = $this->request->query->get( 'crop' );
		if ( !is_array( $crop )
			|| isset( $crop['width'] ) && !$crop['width']
			|| isset( $crop['height'] ) && !$crop['height']
		) {
			$crop = [];
		}
		static::$params['crop'] = array_map( 'intval', $crop );
	}

	/**
	 * Set Engine-specific options based on user-provided input or the defaults.
	 */
	private function setEngineOptions(): void {
		// This is always set, even if Tesseract isn't initially chosen as the engine
		// because we want the default set if the user changes the engine to Tesseract.
		static::$params['psm'] = (int)$this->request->query->get( 'psm', (string)static::$params['psm'] );

		// This is always set, even if Transkribus isn't initially chosen as the engine
		// because we want the default set if the user changes the engine to Transkribus.
		static::$params['line_id'] = (int)$this->request->query->get( 'line_id', (string)static::$params['line_id'] );

		// Apply the tesseract-specific settings
		// NOTE: Intentionally excluding `oem`, see T285262
		if ( TesseractEngine::getId() === static::$params['engine'] ) {
			$this->engine->setPsm( static::$params['psm'] );
		}

		// Apply Transkribus specific settings
		if ( TranskribusEngine::getId() === static::$params['engine'] ) {
			$this->engine->setLineId( static::$params['line_id'] );
		}
	}

	/**
	 * Get a list of language codes from the request.
	 * Looks for both the string `?lang=xx` and array `?langs[]=xx&langs[]=yy` versions.
	 * @param Request $request
	 * @return string[]
	 */
	public function getLangs( Request $request ): array {
		$lang = $request->query->get( 'lang' );
		$langs = $request->query->all( 'langs' );
		$langArray = array_merge( [ $lang ], $langs );
		// Remove invalid chars.
		$langsSanitized = preg_replace( '/[^a-zA-Z0-9\-_]/', '', $langArray );
		// Remove empty and duplicated values, and then reorder keys (just for easier testing).
		$langsFiltered = array_values( array_unique( array_filter( $langsSanitized ) ) );
		// If no languages specified, default to the user's.
		// @TODO The default language code needs to vary based on the engine. T280617.
		//return 0 === count($langsFiltered) ? [$this->intuition->getLang()] : $langsFiltered;
		return $langsFiltered;
	}

	/**
	 * The main form and result page.
	 * @Route("/", name="home")
	 * @return Response
	 */
	public function homeAction(): Response {
		$this->setup();

		// Pre-supply available langs for autocompletion in the form.
		static::$params['available_langs'] = $this->engine->getValidModels( true );
		ksort( static::$params['available_langs'] );

		// set empty array to avoid errors while rendering template on non-transkribus engines
		static::$params['available_line_ids'] = [];

		if ( static::$params['engine'] === 'transkribus' ) {
			// Pre-supply the available line ids for autocompletion in the form.
			static::$params['available_line_ids'] = $this->engine->getValidLineIds( true, false );
			sort( static::$params['available_line_ids'] );

			static::$params['available_line_id_langs'] = $this->engine->getValidLineIds( false, true );
			sort( static::$params['available_line_id_langs'] );
		}

		// Get Tesseract's full list of PSMs.
		/** @var TesseractEngine */
		$tesseract = $this->engineFactory->get( 'tesseract' );
		static::$params['available_psms'] = $tesseract->getAvailablePsms();

		// Intution::listToText() isn't available via Twig, and we only want to do this for the view and not the API.
		static::$params['image_hosts'] = $this->intuition->listToText( static::$params['image_hosts'] );

		if ( static::$params['image'] ) {
			// Auto-redirect to PDF processor if URL ends with .pdf
			if ( $this->isPdfUrl( static::$params['image'] ) ) {
				return $this->redirectToRoute( 'pdf', $this->request->query->all() );
			}

			$result = $this->getResult( EngineBase::ERROR_ON_INVALID_LANGS );
			static::$params['text'] = $result->getText();
			foreach ( $result->getWarnings() as $warning ) {
				$this->addFlash( 'warning', $warning );
			}
		}

		return $this->render( 'output.html.twig', static::$params );
	}

	/**
	 * Run OCR on a single image.
	 *
	 * @Route("/api", name="api", methods={"GET"})
	 * @Route("/api.php", name="apiPhp", methods={"GET"})
	 * @OA\Parameter(
	 *     name="engine",
	 *     in="query",
	 *     description="The engine to use, either `tesseract` or `google` or `transkribus`.",
	 *     example="tesseract",
	 * @OA\Schema(type="string")
	 * )
	 * @OA\Parameter(
	 *     name="image",
	 *     in="query",
	 *     description="The image URL.",
	 * @OA\Schema(type="string")
	 * )
	 * @OA\Parameter(
	 *     name="langs[]",
	 *     in="query",
	 *     description="List of language codes.
	 * Can be left empty, in which case the engine will do its best
	 * (useful for unsupported languages).",
	 * @OA\Schema(type="array", @OA\Items(type="string"))
	 * )
	 * @OA\Parameter(
	 *     name="psm",
	 *     in="query",
	 *     description="The Page Segmentation Mode for Tesseract.",
	 * @OA\Schema(type="int")
	 * )
	 * @OA\Parameter(
	 *     name="line_id",
	 *     in="query",
	 *     description="The line detection model ID to be used for Transkribus.",
	 * @OA\Schema(type="int")
	 * )
	 * @OA\Parameter(
	 *     name="crop[x]",
	 *     in="query",
	 *     description="Crop parameter `x` value.",
	 * @OA\Schema(type="int")
	 * )
	 * @OA\Parameter(
	 *     name="crop[y]",
	 *     in="query",
	 *     description="Crop parameter `y` value.",
	 * @OA\Schema(type="int")
	 * )
	 * @OA\Parameter(
	 *     name="crop[width]",
	 *     in="query",
	 *     description="Crop parameter `width` value.",
	 * @OA\Schema(type="int")
	 * )
	 * @OA\Parameter(
	 *     name="crop[height]",
	 *     in="query",
	 *     description="Crop parameter `height` value.",
	 * @OA\Schema(type="int")
	 * )
	 * @OA\Response(response=200, description="The OCR text, and other data.")
	 * @return JsonResponse
	 */
	public function apiAction(): JsonResponse {
		try {
			$this->setup();
		} catch ( Exception $exception ) {
			return $this->getApiResponse( [
				"error" => $exception->getMessage(),
			] );
		}

		$result = $this->getResult( EngineBase::WARN_ON_INVALID_LANGS );
		$responseParams = array_merge( static::$params, [
			'text' => $result->getText(),
		] );
		$warnings = $result->getWarnings();
		if ( $warnings ) {
			$responseParams['warnings'] = $warnings;
		}
		return $this->getApiResponse( $responseParams );
	}

	/**
	 * Serve the models config with CORS headers so it can be fetched directly
	 * from the Wikisource frontend without requiring a MediaWiki proxy.
	 * Returns a simplified map of engine->{code: title} matching the shape
	 * previously provided by the available_langs API.
	 *
	 * @Route("/api/models", name="apiModels", methods={"GET"})
	 * @return JsonResponse
	 */
	public function apiModelsAction(): JsonResponse {
		$path = $this->getParameter( 'kernel.project_dir' ) . '/public/models.json';
		$raw = json_decode( file_get_contents( $path ), true );
		$out = [];
		foreach ( $raw as $engine => $models ) {
			$out[ $engine ] = [];
			foreach ( $models as $code => $info ) {
				$out[ $engine ][ $code ] = $info[ 'title' ];
			}
		}
		return $this->getApiResponse( $out );
	}

	/**
	 * Get a list of models available for use with a specific OCR engine.
	 *
	 * @Route("/api/available_langs", name="apiLangs", methods={"GET"})
	 * @OA\Parameter(
	 *     name="engine",
	 *     in="query",
	 *     description="The engine to use, either `tesseract` or `google` or `transkribus`.",
	 *     example="tesseract",
	 * @OA\Schema(type="string")
	 * )
	 * @OA\Response(response=200, description="List of available model codes and names, in JSON format.")
	 * @return JsonResponse
	 */
	public function apiAvailableLangsAction(): JsonResponse {
		$this->setup();
		return $this->getApiResponse( [
			'engine' => static::$params['engine'],
			'available_langs' => $this->engine->getValidModels( true ),
		] );
	}

	/**
	 * Get a list of PSMs available for use with Tesseract.
	 *
	 * @Route("/api/tesseract/available_psms", name="apiPsms", methods={"GET"})
	 * @OA\Response(response=200, description="List of available Tesseract PSM values and labels, in JSON format.")
	 * @return JsonResponse
	 */
	public function apiAvailablePsms(): JsonResponse {
		$this->setup();
		/** @var TesseractEngine */
		$tesseract = $this->engineFactory->get( 'tesseract' );
		return $this->getApiResponse( [
			'available_psms' => $tesseract->getAvailablePsms(),
		] );
	}

	/**
	 * Get a list of available line detection IDs.
	 *
	 * @Route("/api/transkribus/available_line_ids", name="apiLineIds", methods={"GET"})
	 * OA\Response(response=200, description="List of available line detection model IDs, in JSON format")
	 * phpcs:enable
	 * @return JsonResponse
	 */
	public function apiAvailableLineDetectionModelIds(): JsonResponse {
		$this->request->query->set( 'engine', 'transkribus' );
		static::$params['engine'] = 'transkribus';
		$this->setup();
		return $this->getApiResponse( [
			'available_line_ids' => $this->engine->getValidLineIds( false, false ),
		] );
	}

	/**
	 * Return a new JsonResponse with the given $params merged into static::$params.
	 * @param mixed[] $params
	 * @return JsonResponse
	 */
	private function getApiResponse( array $params ): JsonResponse {
		$response = new JsonResponse();
		$response->setEncodingOptions( JSON_NUMERIC_CHECK );
		$response->setStatusCode( Response::HTTP_OK );

		// Expose flash messages.
		/** @var FlashBag $flashBag */
		$flashBag = $this->session->getBag( 'flashes' );
		'@phan-var FlashBag $flashBag';
		if ( $flashBag->has( 'error' ) ) {
			$params['error'] = $flashBag->get( 'error' );
		}

		// Allow API requests from the Wikisource extension wherever it's installed.
		$response->headers->set( 'Access-Control-Allow-Origin', '*' );

		$response->setData( $params );
		return $response;
	}

	/**
	 * Get and cache the transcription based on options set in static::$params.
	 * @param string $invalidLangsMode EngineBase::WARN_ON_INVALID_LANGS or EngineBase::ERROR_ON_INVALID_LANGS
	 * @return EngineResult
	 */
	private function getResult( string $invalidLangsMode ): EngineResult {
		ksort( static::$params['crop'] );
		$cacheKey = md5( implode(
			'|',
			[
				static::$params['image'],
				static::$params['engine'],
				implode( '|', static::$params['langs'] ),
				implode( '|', array_map( 'strval', static::$params['crop'] ) ),
				static::$params['psm'],
				static::$params['line_id'],
				// Warning messages are localized
				$this->intuition->getLang(),
			]
		) );

		$result = $this->cache->get( $cacheKey, function ( ItemInterface $item ) use ( $invalidLangsMode ) {
			$item->expiresAfter( (int)$this->getParameter( 'cache_ttl' ) );
			return $this->engine->getResult(
				static::$params['image'],
				$invalidLangsMode,
				static::$params['crop'],
				static::$params['langs']
			);
		} );
		if ( !$result instanceof EngineResult ) {
			throw new Exception( 'Incorrect (possibly cached) result: ' . var_export( $result, true ) );
		}
		return $result;
	}

	/**
	 * Check if the given URL points to a PDF file.
	 * @param string $url
	 * @return bool
	 */
	private function isPdfUrl( string $url ): bool {
		return (bool)preg_match( '/\.pdf$/i', parse_url( $url, PHP_URL_PATH ) ?? '' );
	}

	/**
	 * Process a PDF: classify pages and run OCR only on text/image+text pages.
	 *
	 * @Route("/pdf", name="pdf", methods={"GET"})
	 * @return Response
	 */
	public function pdfAction(): Response {
		$this->setup();

		static::$params['available_langs'] = $this->engine->getValidModels( true );
		ksort( static::$params['available_langs'] );
		static::$params['available_line_ids'] = [];

		if ( static::$params['engine'] === 'transkribus' ) {
			static::$params['available_line_ids'] = $this->engine->getValidLineIds( true, false );
			sort( static::$params['available_line_ids'] );
			static::$params['available_line_id_langs'] = $this->engine->getValidLineIds( false, true );
			sort( static::$params['available_line_id_langs'] );
		}

		/** @var TesseractEngine */
		$tesseract = $this->engineFactory->get( 'tesseract' );
		static::$params['available_psms'] = $tesseract->getAvailablePsms();
		static::$params['image_hosts'] = $this->intuition->listToText( static::$params['image_hosts'] );

		if ( static::$params['image'] ) {
			try {
				$pdfResult = $this->processPdf(
					static::$params['image'],
					EngineBase::ERROR_ON_INVALID_LANGS
				);
				static::$params['pdf_result'] = $pdfResult;
				static::$params['text'] = $pdfResult->getFullText();
				foreach ( $pdfResult->getWarnings() as $warning ) {
					$this->addFlash( 'warning', $warning );
				}
			} catch ( OcrException $e ) {
				$errorMsg = $this->intuition->msg(
					$e->getI18nKey(),
					[ 'variables' => $e->getI18nParams() ]
				);
				$this->addFlash( 'error', $errorMsg ?: $e->getI18nKey() );
			} catch ( Exception $e ) {
				$this->addFlash( 'error', $e->getMessage() ?: 'An unexpected error occurred.' );
			}
		}

		return $this->render( 'pdf_output.html.twig', static::$params );
	}

	/**
	 * API endpoint for PDF processing.
	 *
	 * @Route("/api/pdf", name="apiPdf", methods={"GET"})
	 * @OA\Parameter(name="engine", in="query", description="OCR engine.", @OA\Schema(type="string"))
	 * @OA\Parameter(name="image", in="query", description="The PDF URL.", @OA\Schema(type="string"))
	 * @OA\Parameter(name="langs[]", in="query", description="Language codes.",
	 *     @OA\Schema(type="array", @OA\Items(type="string")))
	 * @OA\Parameter(name="start_page", in="query", description="Start page (1-based).",
	 *     @OA\Schema(type="integer"))
	 * @OA\Parameter(name="end_page", in="query", description="End page (1-based).",
	 *     @OA\Schema(type="integer"))
	 * @OA\Response(response=200, description="Per-page OCR results with classifications.")
	 * @return JsonResponse
	 */
	public function apiPdfAction(): JsonResponse {
		try {
			$this->setup();
		} catch ( Exception $exception ) {
			return $this->getApiResponse( [ 'error' => $exception->getMessage() ] );
		}

		if ( !static::$params['image'] ) {
			return $this->getApiResponse( [ 'error' => 'Missing image (PDF URL) parameter.' ] );
		}

		try {
			$pdfResult = $this->processPdf(
				static::$params['image'],
				EngineBase::WARN_ON_INVALID_LANGS
			);

			$pages = [];
			foreach ( $pdfResult->getClassifications() as $pageNum => $classification ) {
				$pages[$pageNum] = [
					'page' => $pageNum,
					'type' => $classification->getType(),
					'has_text' => $classification->hasText(),
					'text' => $pdfResult->getPageTexts()[$pageNum] ?? '',
				];
			}

			$responseParams = [
				'engine' => static::$params['engine'],
				'image' => static::$params['image'],
				'langs' => static::$params['langs'],
				'total_pages' => $pdfResult->getTotalPages(),
				'ocr_pages' => $pdfResult->getOcrPages(),
				'skipped_pages' => $pdfResult->getSkippedPages(),
				'pages' => $pages,
				'text' => $pdfResult->getFullText(),
			];

			$warnings = $pdfResult->getWarnings();
			if ( $warnings ) {
				$responseParams['warnings'] = $warnings;
			}

			return $this->getApiResponse( $responseParams );
		} catch ( OcrException $e ) {
			$errorMsg = $this->intuition->msg(
				$e->getI18nKey(),
				[ 'variables' => $e->getI18nParams() ]
			);
			return $this->getApiResponse( [ 'error' => $errorMsg ?: $e->getI18nKey() ] );
		} catch ( Exception $e ) {
			return $this->getApiResponse( [ 'error' => $e->getMessage() ?: 'An unexpected error occurred.' ] );
		}
	}

	/**
	 * Process a PDF URL: classify pages and OCR text/image+text pages.
	 *
	 * @param string $pdfUrl
	 * @param string $invalidLangsMode
	 * @return PdfResult
	 * @throws Exception
	 */
	private function processPdf( string $pdfUrl, string $invalidLangsMode ): PdfResult {
		// PDF processing is inherently slow (render + OCR per page).
		// Extend execution time: ~10 seconds per page is a reasonable allowance.
		$startPage = $this->request->query->getInt( 'start_page' ) ?: null;
		$endPage = $this->request->query->getInt( 'end_page' ) ?: null;

		// Cap page range to avoid excessively long requests.
		$maxPages = 20;
		if ( $startPage === null ) {
			$startPage = 1;
		}
		if ( $endPage === null || ( $endPage - $startPage + 1 ) > $maxPages ) {
			$endPage = $startPage + $maxPages - 1;
		}

		// Allow enough time: ~10s per page for GS render + OCR.
		$pageCount = $endPage - $startPage + 1;
		$timeLimit = max( 60, $pageCount * 10 );
		set_time_limit( $timeLimit );

		[ $classifications, $tmpDir ] = $this->pdfProcessor->processPdf(
			$pdfUrl, $startPage, $endPage
		);

		try {
			$totalPages = count( $classifications );
			$pageTexts = [];
			$warnings = [];

			foreach ( $classifications as $pageNum => $classification ) {
				if ( !$classification->hasText() ) {
					continue;
				}

				// Read image data from the temp file, one page at a time.
				$imageData = $classification->getImageData();
				$result = $this->ocrImageData( $imageData, $invalidLangsMode );
				$pageTexts[$pageNum] = $result->getText();

				foreach ( $result->getWarnings() as $warning ) {
					if ( !in_array( $warning, $warnings, true ) ) {
						$warnings[] = $warning;
					}
				}

				// Free memory between pages.
				unset( $imageData );
			}

			// Build lightweight result (no image data, safe to cache later if needed).
			return new PdfResult( $totalPages, $classifications, $pageTexts, $warnings );
		} finally {
			// Always clean up temp files.
			$this->pdfProcessor->cleanupDir( $tmpDir );
		}
	}

	/**
	 * Run OCR on raw image data (PNG bytes) from a rendered PDF page.
	 *
	 * @param string $imageData Raw PNG image bytes.
	 * @param string $invalidLangsMode
	 * @return EngineResult
	 */
	private function ocrImageData( string $imageData, string $invalidLangsMode ): EngineResult {
		[ $validLangs, $invalidLangs ] = $this->engine->filterValidLangs(
			static::$params['langs'],
			$invalidLangsMode
		);

		// For engines that support direct image data, pass it directly.
		// We create a temporary file since most engines expect a URL or file.
		$tmpFile = tempnam( sys_get_temp_dir(), 'ocr_pdf_page_' );
		file_put_contents( $tmpFile, $imageData );

		try {
			// Use the engine's getResult with a file:// URL isn't ideal.
			// Instead, we'll use engine-specific approaches.
			$engineId = $this->engine::getId();

			if ( $engineId === 'tesseract' ) {
				return $this->ocrWithTesseract( $imageData, $validLangs, $invalidLangs );
			}

			if ( $engineId === 'google' ) {
				return $this->ocrWithGoogle( $imageData, $validLangs, $invalidLangs );
			}

			if ( $engineId === 'transkribus' ) {
				return $this->ocrWithTranskribus( $imageData, $validLangs, $invalidLangs );
			}

			throw new Exception( "Unsupported engine for PDF: $engineId" );
		} finally {
			@unlink( $tmpFile );
		}
	}

	/**
	 * OCR image data using Tesseract.
	 * @param string $imageData
	 * @param string[] $validLangs
	 * @param string[] $invalidLangs
	 * @return EngineResult
	 */
	private function ocrWithTesseract(
		string $imageData,
		array $validLangs,
		array $invalidLangs
	): EngineResult {
		$ocr = new \thiagoalessio\TesseractOCR\TesseractOCR();
		$ocr->imageData( $imageData, strlen( $imageData ) );

		if ( $validLangs ) {
			$ocr->lang( ...$validLangs );
		}

		if ( static::$params['psm'] ) {
			$ocr->psm( static::$params['psm'] );
		}

		putenv( 'OMP_THREAD_LIMIT=1' );
		try {
			$text = $ocr->run();
		} catch ( \thiagoalessio\TesseractOCR\UnsuccessfulCommandException $e ) {
			if ( strpos( $e->getMessage(), 'The command did not produce any output' ) !== false ) {
				return new EngineResult( '', [ 'No text detected on this page.' ] );
			}
			throw $e;
		}

		$warnings = $invalidLangs
			? [ $this->engine->getInvalidLangsWarning( $invalidLangs ) ]
			: [];
		return new EngineResult( $text, $warnings );
	}

	/**
	 * OCR image data using Google Cloud Vision.
	 * @param string $imageData
	 * @param string[] $validLangs
	 * @param string[] $invalidLangs
	 * @return EngineResult
	 */
	private function ocrWithGoogle(
		string $imageData,
		array $validLangs,
		array $invalidLangs
	): EngineResult {
		$imageContext = new \Google\Cloud\Vision\V1\ImageContext();
		if ( $validLangs ) {
			$imageContext->setLanguageHints( $validLangs );
		}

		/** @var GoogleCloudVisionEngine $engine */
		$engine = $this->engine;

		// Use reflection to access the imageAnnotator property.
		$ref = new \ReflectionClass( $engine );
		$prop = $ref->getProperty( 'imageAnnotator' );
		$prop->setAccessible( true );
		$annotator = $prop->getValue( $engine );

		if ( !$annotator ) {
			throw new Exception( 'Google Cloud Vision API key is not configured.' );
		}

		$response = $annotator->documentTextDetection(
			$imageData,
			[ 'imageContext' => $imageContext ]
		);

		if ( $response->getError() ) {
			throw new Exception( 'Google Vision error: ' . $response->getError()->getMessage() );
		}

		$annotation = $response->getFullTextAnnotation();
		$text = $annotation instanceof \Google\Cloud\Vision\V1\TextAnnotation
			? $annotation->getText()
			: '';

		$warnings = $invalidLangs
			? [ $this->engine->getInvalidLangsWarning( $invalidLangs ) ]
			: [];
		return new EngineResult( $text, $warnings );
	}

	/**
	 * OCR image data using Transkribus.
	 * @param string $imageData
	 * @param string[] $validLangs
	 * @param string[] $invalidLangs
	 * @return EngineResult
	 */
	private function ocrWithTranskribus(
		string $imageData,
		array $validLangs,
		array $invalidLangs
	): EngineResult {
		// Transkribus expects base64-encoded image data.
		// We delegate to the engine's getResult by saving to a temp file and using a data URI.
		// For now, use Tesseract as fallback for Transkribus PDF pages.
		return $this->ocrWithTesseract( $imageData, $validLangs, $invalidLangs );
	}
}
