<?php
declare( strict_types = 1 );

namespace App\Engine;

use App\Exception\OcrException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Handles downloading PDFs, converting pages to images, and classifying each page
 * as Text, Image, or Image+Text using histogram-based analysis.
 *
 * Only pages classified as Text or Image+Text are sent for OCR.
 */
class PdfProcessor {

	/** @var int DPI for rendering PDF pages. 150 balances quality and memory usage. */
	private const RENDER_DPI = 150;

	/**
	 * Thresholds for classification based on dark-pixel coverage ratio.
	 * - Below TEXT_MAX_RATIO: mostly white page with text lines -> Text
	 * - Above IMAGE_MIN_RATIO: heavily inked page (full-page image) -> Image
	 * - Between: mix of images and text -> Image+Text
	 */
	private const TEXT_MAX_RATIO = 0.15;
	private const IMAGE_MIN_RATIO = 0.45;

	/** @var HttpClientInterface */
	private HttpClientInterface $httpClient;

	/** @var string[] Allowed image hosts. */
	private array $imageHosts = [];

	public function __construct( HttpClientInterface $httpClient ) {
		$this->httpClient = $httpClient;
	}

	/**
	 * Set allowed image hosts (same as used for image validation).
	 * @param string $imageHosts Comma-separated list.
	 */
	public function setImageHosts( string $imageHosts ): void {
		$this->imageHosts = array_map( 'trim', explode( ',', $imageHosts ) );
	}

	/**
	 * Check that a PDF URL is valid and from an allowed host.
	 * @param string $pdfUrl
	 * @throws OcrException
	 */
	public function checkPdfUrl( string $pdfUrl ): void {
		$hostRegex = implode( '|', array_map( 'preg_quote', $this->imageHosts ) );
		$regex = "/^https?:\/\/($hostRegex)\/.+\.pdf$/i";
		if ( preg_match( $regex, $pdfUrl ) !== 1 ) {
			throw new OcrException( 'image-url-error', [ count( $this->imageHosts ),
				implode( ', ', $this->imageHosts ) ] );
		}
	}

	/**
	 * Download a PDF from a URL.
	 * @param string $pdfUrl
	 * @return string Raw PDF data.
	 * @throws OcrException
	 */
	public function downloadPdf( string $pdfUrl ): string {
		$response = $this->httpClient->request( 'GET', $pdfUrl, [
			'timeout' => 300,
			'headers' => [
				'User-Agent' => 'WikimediaOCR/1.0 (https://ocr.wmcloud.org/)',
			],
		] );

		if ( $response->getStatusCode() !== 200 ) {
			throw new OcrException( 'image-retrieval-failed',
				[ "HTTP {$response->getStatusCode()} fetching PDF" ] );
		}

		return $response->getContent();
	}

	/**
	 * Find Ghostscript binary path.
	 * @return string|null
	 */
	private function findGhostscript(): ?string {
		$absolutePaths = [ '/opt/homebrew/bin/gs', '/usr/local/bin/gs', '/usr/bin/gs' ];
		foreach ( $absolutePaths as $path ) {
			if ( is_executable( $path ) ) {
				return $path;
			}
		}
		$output = [];
		$exitCode = 0;
		@exec( 'which gs 2>/dev/null', $output, $exitCode );
		if ( $exitCode === 0 && !empty( $output[0] ) ) {
			return trim( $output[0] );
		}
		return null;
	}

	/**
	 * Render PDF pages to temporary PNG files via Ghostscript.
	 * Returns the temp directory and page count. Caller must clean up via cleanupDir().
	 *
	 * @param string $pdfData Raw PDF bytes.
	 * @param int|null $startPage 1-based start page (inclusive).
	 * @param int|null $endPage 1-based end page (inclusive).
	 * @return array{string, int, int} [ tmpDir, firstPageNum, lastPageNum ]
	 * @throws OcrException
	 */
	private function renderPdfPages(
		string $pdfData,
		?int $startPage = null,
		?int $endPage = null
	): array {
		$gsPath = $this->findGhostscript();
		if ( $gsPath === null && extension_loaded( 'imagick' ) ) {
			return $this->renderPdfPagesViaImagick( $pdfData, $startPage, $endPage );
		}
		if ( $gsPath === null ) {
			throw new OcrException( 'pdf-imagick-missing', [] );
		}

		$tmpDir = sys_get_temp_dir() . '/ocr_pdf_' . uniqid();
		@mkdir( $tmpDir, 0777, true );
		$tmpPdf = $tmpDir . '/input.pdf';
		file_put_contents( $tmpPdf, $pdfData );

		$outputPattern = $tmpDir . '/page_%04d.png';

		// Build GS command with optional page range.
		$pageArgs = '';
		if ( $startPage !== null && $startPage > 0 ) {
			$pageArgs .= " -dFirstPage=$startPage";
		}
		if ( $endPage !== null && $endPage > 0 ) {
			$pageArgs .= " -dLastPage=$endPage";
		}

		$cmd = sprintf(
			'%s -dBATCH -dNOPAUSE -dQUIET -sDEVICE=png16m -r%d%s -sOutputFile=%s %s 2>&1',
			escapeshellarg( $gsPath ),
			self::RENDER_DPI,
			$pageArgs,
			escapeshellarg( $outputPattern ),
			escapeshellarg( $tmpPdf )
		);

		exec( $cmd, $output, $exitCode );
		if ( $exitCode !== 0 ) {
			$this->cleanupDir( $tmpDir );
			throw new OcrException( 'pdf-processing-error',
				[ 'Ghostscript failed (exit ' . $exitCode . '): ' . implode( ' ', $output ) ] );
		}

		// Count rendered pages.
		$firstNum = $startPage ?? 1;
		$pageCount = count( glob( $tmpDir . '/page_*.png' ) );
		$lastNum = $firstNum + $pageCount - 1;

		return [ $tmpDir, $firstNum, $lastNum ];
	}

	/**
	 * Render PDF pages via Imagick (alternative to Ghostscript).
	 * @param string $pdfData
	 * @param int|null $startPage
	 * @param int|null $endPage
	 * @return array{string, int, int}
	 * @throws OcrException
	 */
	private function renderPdfPagesViaImagick(
		string $pdfData,
		?int $startPage = null,
		?int $endPage = null
	): array {
		$tmpDir = sys_get_temp_dir() . '/ocr_pdf_' . uniqid();
		@mkdir( $tmpDir, 0777, true );

		try {
			$imagick = new \Imagick();
			$imagick->setResolution( self::RENDER_DPI, self::RENDER_DPI );
			$imagick->readImageBlob( $pdfData );
			$totalPages = $imagick->getNumberImages();

			$first = $startPage ?? 1;
			$last = $endPage ?? $totalPages;
			$last = min( $last, $totalPages );

			for ( $i = $first - 1; $i < $last; $i++ ) {
				$imagick->setIteratorIndex( $i );
				$imagick->setImageFormat( 'png' );
				$flattened = $imagick->mergeImageLayers( \Imagick::LAYERMETHOD_FLATTEN );
				$pageNum = $i + 1;
				$outFile = sprintf( $tmpDir . '/page_%04d.png', $pageNum - $first + 1 );
				$flattened->writeImage( $outFile );
				$flattened->destroy();
			}
			$imagick->destroy();

			return [ $tmpDir, $first, $last ];
		} catch ( \ImagickException $e ) {
			$this->cleanupDir( $tmpDir );
			throw new OcrException( 'pdf-processing-error', [ $e->getMessage() ] );
		}
	}

	/**
	 * Get the path to a rendered page image file.
	 * @param string $tmpDir
	 * @param int $fileIndex 1-based file index within the rendered batch.
	 * @return string|null File path, or null if not found.
	 */
	private function getPageFilePath( string $tmpDir, int $fileIndex ): ?string {
		$path = sprintf( $tmpDir . '/page_%04d.png', $fileIndex );
		return file_exists( $path ) ? $path : null;
	}

	/**
	 * Classify a single page image as Text, Image, or Image+Text.
	 *
	 * Uses a fast histogram approach: sample pixels, compute dark-pixel ratio,
	 * and classify based on how much of the page is covered with ink.
	 *
	 * @param string $imagePath Path to the page PNG file.
	 * @param int $pageNumber Page number (1-based).
	 * @return PdfPageClassification
	 */
	public function classifyPage( string $imagePath, int $pageNumber ): PdfPageClassification {
		$gdImage = @imagecreatefrompng( $imagePath );
		if ( $gdImage === false ) {
			// Can't analyse; assume text (safe OCR default).
			return new PdfPageClassification(
				$pageNumber, PdfPageClassification::TYPE_TEXT, $imagePath
			);
		}

		$width = imagesx( $gdImage );
		$height = imagesy( $gdImage );

		$darkRatio = $this->computeDarkPixelRatio( $gdImage, $width, $height );
		imagedestroy( $gdImage );

		if ( $darkRatio < self::TEXT_MAX_RATIO ) {
			$type = PdfPageClassification::TYPE_TEXT;
		} elseif ( $darkRatio >= self::IMAGE_MIN_RATIO ) {
			$type = PdfPageClassification::TYPE_IMAGE;
		} else {
			$type = PdfPageClassification::TYPE_IMAGE_TEXT;
		}

		return new PdfPageClassification( $pageNumber, $type, $imagePath );
	}

	/**
	 * Compute the ratio of dark pixels in the image by sampling.
	 * Uses a grid sample (every Nth pixel) for speed.
	 *
	 * @param \GdImage $image
	 * @param int $width
	 * @param int $height
	 * @return float 0.0 (all white) to 1.0 (all dark).
	 */
	private function computeDarkPixelRatio( \GdImage $image, int $width, int $height ): float {
		// Sample every 4th pixel in each direction for speed.
		$step = 4;
		$darkCount = 0;
		$totalCount = 0;
		$threshold = 200; // Pixels darker than this are "ink".

		imagefilter( $image, IMG_FILTER_GRAYSCALE );

		for ( $y = 0; $y < $height; $y += $step ) {
			for ( $x = 0; $x < $width; $x += $step ) {
				$gray = imagecolorat( $image, $x, $y ) & 0xFF;
				if ( $gray < $threshold ) {
					$darkCount++;
				}
				$totalCount++;
			}
		}

		return $totalCount > 0 ? $darkCount / $totalCount : 0.0;
	}

	/**
	 * Process an entire PDF: download, render, classify, and return classifications.
	 * Pages are processed from temporary files to avoid holding all images in memory.
	 *
	 * @param string $pdfUrl URL to the PDF.
	 * @param int|null $startPage Optional start page (1-based, inclusive).
	 * @param int|null $endPage Optional end page (1-based, inclusive).
	 * @return array{PdfPageClassification[], string} [ classifications, tmpDir ]
	 *         Caller MUST call cleanupDir($tmpDir) when done with the page images.
	 * @throws OcrException
	 */
	public function processPdf(
		string $pdfUrl,
		?int $startPage = null,
		?int $endPage = null
	): array {
		$this->checkPdfUrl( $pdfUrl );
		$pdfData = $this->downloadPdf( $pdfUrl );
		[ $tmpDir, $firstPageNum, $lastPageNum ] = $this->renderPdfPages(
			$pdfData, $startPage, $endPage
		);
		// Free PDF data immediately.
		unset( $pdfData );

		$classifications = [];
		$fileIndex = 1;
		for ( $pageNum = $firstPageNum; $pageNum <= $lastPageNum; $pageNum++ ) {
			$pagePath = $this->getPageFilePath( $tmpDir, $fileIndex );
			if ( $pagePath === null ) {
				$fileIndex++;
				continue;
			}
			$classifications[$pageNum] = $this->classifyPage( $pagePath, $pageNum );
			$fileIndex++;
		}

		return [ $classifications, $tmpDir ];
	}

	/**
	 * Remove a temporary directory and all its contents.
	 * @param string $dir
	 */
	public function cleanupDir( string $dir ): void {
		if ( !is_dir( $dir ) ) {
			return;
		}
		$files = glob( $dir . '/*' );
		if ( $files ) {
			foreach ( $files as $file ) {
				@unlink( $file );
			}
		}
		@rmdir( $dir );
	}
}
