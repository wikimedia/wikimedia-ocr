<?php
declare( strict_types = 1 );

namespace App\Tests\Twig;

use App\Engine\TesseractEngine;
use App\Tests\OcrTestCase;
use App\Twig\AppExtension;
use Krinkle\Intuition\Intuition;
use Symfony\Component\HttpClient\MockHttpClient;
use thiagoalessio\TesseractOCR\TesseractOCR;

class AppExtensionTest extends OcrTestCase {
	/** @var AppExtension */
	protected $ext;

	public function setUp(): void {
		parent::setUp();
		$tesseractEngine = new TesseractEngine(
							new MockHttpClient(),
							new Intuition(),
							$this->projectDir,
							new TesseractOCR()
						);
		$transkribusEngine = new TranskribusEngine(
								new TranskribusClient(
									getenv( 'APP_TRANSKRIBUS_ACCESS_TOKEN' ),
									getenv( 'APP_TRANSKRIBUS_REFRESH_TOKEN' ),
									new MockHttpClient()
								),
								$intuition,
								$this->projectDir,
								new MockHttpClient()
							);
		$this->ext = new AppExtension( $engine );
	}

	/**
	 * @covers AppExtension::getOcrLangName
	 */
	public function testOcrLangName(): void {
		// Non-standard language code with name defined in EngineBase::LANG_NAMES
		static::assertSame( 'Azərbaycan (qədim yazı)', $this->ext->getOcrLangName( 'az-cyrl' ) );

		// Standard language code (name provided by Intuition)
		static::assertSame( 'English', $this->ext->getOcrLangName( 'en' ) );
	}
}
