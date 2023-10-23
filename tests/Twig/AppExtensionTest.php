<?php
declare( strict_types = 1 );

namespace App\Tests\Twig;

use App\Engine\TesseractEngine;
use App\Engine\TranskribusClient;
use App\Engine\TranskribusEngine;
use App\Tests\OcrTestCase;
use App\Twig\AppExtension;
use Krinkle\Intuition\Intuition;
use Symfony\Component\Cache\Adapter\NullAdapter;
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
									getenv( 'APP_TRANSKRIBUS_USERNAME' ),
									getenv( 'APP_TRANSKRIBUS_PASSWORD' ),
									new MockHttpClient(),
									new NullAdapter(),
									new NullAdapter()
								),
								new Intuition(),
								$this->projectDir,
								new MockHttpClient()
							);
		$this->ext = new AppExtension( $tesseractEngine, $transkribusEngine );
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

	/**
	 * @covers AppExtension::getLineIdName
	 */
	public function testLineIdName(): void {
		static::assertSame( 'Balinese Line Detection Model', $this->ext->getLineIdName( 'bali' ) );
	}
}
