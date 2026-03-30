<?php
declare( strict_types = 1 );

namespace App\Engine;

/**
 * Immutable value object representing a classified PDF page.
 */
class PdfPageClassification {

	public const TYPE_TEXT = 'Text';
	public const TYPE_IMAGE = 'Image';
	public const TYPE_IMAGE_TEXT = 'Image+Text';

	/** @var int Page number (1-based). */
	private int $pageNumber;

	/** @var string One of the TYPE_* constants. */
	private string $type;

	/** @var string File path to the rendered page PNG. */
	private string $imagePath;

	public function __construct( int $pageNumber, string $type, string $imagePath ) {
		$this->pageNumber = $pageNumber;
		$this->type = $type;
		$this->imagePath = $imagePath;
	}

	public function getPageNumber(): int {
		return $this->pageNumber;
	}

	public function getType(): string {
		return $this->type;
	}

	/**
	 * Get path to the page image file.
	 */
	public function getImagePath(): string {
		return $this->imagePath;
	}

	/**
	 * Read the page image data from disk.
	 * @return string PNG image bytes.
	 */
	public function getImageData(): string {
		return file_get_contents( $this->imagePath );
	}

	/**
	 * Whether this page contains text that should be OCR'd.
	 */
	public function hasText(): bool {
		return $this->type === self::TYPE_TEXT || $this->type === self::TYPE_IMAGE_TEXT;
	}
}
