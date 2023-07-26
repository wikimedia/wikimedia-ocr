<?php
declare( strict_types = 1 );

namespace App\Twig;

use App\Engine\TesseractEngine;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension {
	/** @var TesseractEngine */
	protected $engine;

	/**
	 * AppExtension constructor.
	 * @param TesseractEngine $engine
	 */
	public function __construct( TesseractEngine $engine ) {
		$this->engine = $engine;
	}

	/**
	 * Registry of custom TwigFunctions.
	 * @return TwigFunction[]
	 */
	public function getFunctions(): array {
		return [
			new TwigFunction( 'ocr_lang_name', [ $this, 'getOcrLangName' ] ),
			new TwigFunction( 'line_id_name', [ $this, 'getLineIdName' ] ),
		];
	}

	/**
	 * Registry of custom TwigFilters.
	 * @return TwigFilter[]
	 */
	public function getFilters(): array {
		return [
			new TwigFilter( 'textarea_rows', [ $this, 'getTextareaRows' ] ),
		];
	}

	/**
	 * Get the number of rows a textarea should be based on the size of the given text.
	 * @param string $text
	 * @return int
	 */
	public function getTextareaRows( string $text ): int {
		return max( 10, substr_count( $text, "\n" ) );
	}

	/**
	 * Get the name of the given language. This adds a few translations that don't exist in Intuition.
	 * @param string|null $lang
	 * @return string
	 */
	public function getOcrLangName( ?string $lang = null ): string {
		return $this->engine->getLangName( $lang );
	}

	/**
	 * Get the name of the given line detection model ID.
	 * @param string|null $lineIdLang
	 * @return string
	 */
	public function getLineIdName( ?string $lineIdLang = null ): string {
		return $this->engine->getLineIdModelName( $lineIdLang );
	}
}
