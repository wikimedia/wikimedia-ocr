<?php
declare( strict_types = 1 );

namespace App\Engine;

/**
 * Immutable value object representing the OCR result from processing a PDF.
 */
class PdfResult {

	/** @var int Total number of pages in the PDF. */
	private int $totalPages;

	/** @var array<int, PdfPageClassification> Page classifications keyed by page number. */
	private array $classifications;

	/** @var array<int, string> OCR text keyed by page number. */
	private array $pageTexts;

	/** @var string[] Warning messages. */
	private array $warnings;

	/**
	 * @param int $totalPages
	 * @param PdfPageClassification[] $classifications
	 * @param array<int, string> $pageTexts
	 * @param string[] $warnings
	 */
	public function __construct(
		int $totalPages,
		array $classifications,
		array $pageTexts,
		array $warnings = []
	) {
		$this->totalPages = $totalPages;
		$this->classifications = $classifications;
		$this->pageTexts = $pageTexts;
		$this->warnings = $warnings;
	}

	public function getTotalPages(): int {
		return $this->totalPages;
	}

	/**
	 * @return PdfPageClassification[]
	 */
	public function getClassifications(): array {
		return $this->classifications;
	}

	/**
	 * @return array<int, string>
	 */
	public function getPageTexts(): array {
		return $this->pageTexts;
	}

	/**
	 * Get all extracted text concatenated, with page separators.
	 */
	public function getFullText(): string {
		$texts = [];
		ksort( $this->pageTexts );
		foreach ( $this->pageTexts as $pageNum => $text ) {
			if ( trim( $text ) !== '' ) {
				$texts[] = "--- Page $pageNum ---\n$text";
			}
		}
		return implode( "\n\n", $texts );
	}

	/**
	 * @return string[]
	 */
	public function getWarnings(): array {
		return $this->warnings;
	}

	/**
	 * Get page numbers by classification type.
	 * @param string $type One of PdfPageClassification::TYPE_* constants.
	 * @return int[]
	 */
	public function getPagesByType( string $type ): array {
		$pages = [];
		foreach ( $this->classifications as $classification ) {
			if ( $classification->getType() === $type ) {
				$pages[] = $classification->getPageNumber();
			}
		}
		return $pages;
	}

	/**
	 * Get pages that were OCR'd (text and image+text pages).
	 * @return int[]
	 */
	public function getOcrPages(): array {
		$pages = [];
		foreach ( $this->classifications as $classification ) {
			if ( $classification->hasText() ) {
				$pages[] = $classification->getPageNumber();
			}
		}
		return $pages;
	}

	/**
	 * Get pages that were skipped (image-only pages).
	 * @return int[]
	 */
	public function getSkippedPages(): array {
		return $this->getPagesByType( PdfPageClassification::TYPE_IMAGE );
	}
}
