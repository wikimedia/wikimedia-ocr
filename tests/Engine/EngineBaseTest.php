<?php
declare( strict_types = 1 );

namespace App\Tests\Engine;

use App\Engine\EngineBase;
use App\Engine\GoogleCloudVisionEngine;
use App\Engine\TesseractEngine;
use App\Engine\TranskribusClient;
use App\Engine\TranskribusEngine;
use App\Exception\OcrException;
use App\Tests\OcrTestCase;
use Krinkle\Intuition\Intuition;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use thiagoalessio\TesseractOCR\TesseractOCR;

class EngineBaseTest extends OcrTestCase {
	/** @var GoogleCloudVisionEngine */
	private $googleEngine;

	/** @var TesseractEngine */
	private $tesseractEngine;

	/** @var TranskribusEngine */
	private $transkribusEngine;

	public function setUp(): void {
		parent::setUp();

		$this->googleEngine = $this->instantiateEngine( 'google' );

		$this->tesseractEngine = $this->instantiateEngine( 'tesseract' );

		$this->transkribusEngine = $this->instantiateEngine( 'transkribus' );
	}

	/**
	 * @covers EngineBase::checkImageUrl
	 * @dataProvider provideCheckImageUrl()
	 */
	public function testCheckImageUrl( string $url, bool $exceptionExpected ): void {
		$this->tesseractEngine->setImageHosts( 'upload.wikimedia.org,localhost' );
		if ( $exceptionExpected ) {
			static::expectException( OcrException::class );
		}
		$this->tesseractEngine->checkImageUrl( $url );
		static::assertTrue( true );
	}

	/**
	 * @return mixed[][]
	 */
	public function provideCheckImageUrl(): array {
		return [
			// Pass:
			[ 'https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg', false ],
			[ 'https://upload.wikimedia.org/wikipedia/commons/file.jpg', false ],
			// Fail:
			[ 'https://foo.example.com/wikipedia/commons/a/a9/Example.jpg', true ],
			[ 'https://en.wikisource.org/file.mov', true ],
			[ 'https://localhosts/file.jpg', true ],
		];
	}

	/**
	 * @covers EngineBase::filterValidLangs for Google Engine
	 */
	public function testFilterValidLangsGoogleEngine(): void {
		$allValid = [ 'en', 'es' ];
		$allValidPlusInvalid = array_merge( $allValid, [ 'this-is-invalid' ] );
		$this->assertSame(
			$allValid,
			$this->googleEngine->filterValidLangs( $allValid, EngineBase::WARN_ON_INVALID_LANGS )[0]
		);
		$this->assertSame(
			$allValid,
			$this->googleEngine->filterValidLangs( $allValidPlusInvalid, EngineBase::WARN_ON_INVALID_LANGS )[0]
		);

		$this->expectException( OcrException::class );
		$this->googleEngine->filterValidLangs( $allValidPlusInvalid, EngineBase::ERROR_ON_INVALID_LANGS );
	}

	/**
	 * @param EngineBase $engine
	 * @param string[] $langs Language codes
	 * @param string[] $validLangs
	 * @param string $invalidLangsMode
	 * @covers EngineBase::filterValidLangs for Tesseract Engine
	 * @dataProvider provideLangs
	 */
	public function testFilterValidLangs(
		EngineBase $engine, array $langs, array $validLangs, string $invalidLangsMode
	): void {
		if ( EngineBase::WARN_ON_INVALID_LANGS === $invalidLangsMode ) {
			$this->assertSame( $validLangs, $engine->filterValidLangs( $langs, $invalidLangsMode )[0] );
		} else {
			if ( $langs !== $validLangs ) {
				$this->expectException( OcrException::class );
			}
			$engine->filterValidLangs( $langs, $invalidLangsMode );
			$this->addToAssertionCount( 1 );
		}
	}

	/**
	 * @return array
	 */
	public function provideLangs(): array {
		return [
			[
				'engine' => $this->instantiateEngine( 'tesseract' ),
				[ 'en', 'fr' ],
				[ 'en', 'fr' ],
				EngineBase::WARN_ON_INVALID_LANGS,
				EngineBase::ERROR_ON_INVALID_LANGS,
			],
			[
				'engine' => $this->instantiateEngine( 'transkribus' ),
				[ 'en-b2022', 'fr-m1' ],
				[ 'en-b2022', 'fr-m1' ],
				EngineBase::WARN_ON_INVALID_LANGS,
				EngineBase::ERROR_ON_INVALID_LANGS,
			],

		];
	}

	/**
	 * @covers EngineBase::getLangCodes
	 */
	public function testLangCodes(): void {
		static::assertSame( [ 'eng', 'fra' ], $this->tesseractEngine->getLangCodes( [ 'en', 'fr' ] ) );
		static::assertSame( [ 'en', 'iw' ], $this->googleEngine->getLangCodes( [ 'en', 'he' ] ) );
	}

	/**
	 * @covers EngineBase::getLangName
	 * @covers EngineBase::getValidLangs
	 */
	public function testLangNames(): void {
		// From Intuition.
		static::assertSame( 'français', $this->tesseractEngine->getLangName( 'fr' ) );

		// From EngineBase::LANG_NAMES
		static::assertSame( 'moyen français (1400-1600)', $this->tesseractEngine->getLangName( 'frm' ) );

		// Make sure every language has a name.
		foreach ( $this->tesseractEngine->getValidLangs( true ) as $lang => $name ) {
			static::assertNotEmpty( $name, "Missing lang name for '$lang'" );
		}
		foreach ( $this->googleEngine->getValidLangs( true ) as $lang => $name ) {
			static::assertNotEmpty( $name, "Missing lang name for '$lang'" );
		}
	}

	/**
	 * @covers EngineBase::getValidLineIds
	 */
	public function testValidLineIds(): void {
		static::assertNotEmpty( $this->transkribusEngine->getValidLineIds( false, false ), "Missing line IDs" );
		static::assertNotEmpty( $this->transkribusEngine->getValidLineIds( false, true ), "Missing line ID langs" );
		static::assertNotEmpty( $this->transkribusEngine->getValidLineIds( true, false ), "Missing line IDs" );
		static::assertNotEmpty( $this->transkribusEngine->getValidLineIds( true, true ), "Missing line IDs" );
	}

	public function instantiateEngine( string $engineName ): EngineBase {
		self::bootKernel();
		$this->projectDir = self::$kernel->getProjectDir();
		$intuition = new Intuition();
		$engine = null;

		switch ( $engineName ) {
			case 'tesseract':
				$tesseractOCR = $this->getMockBuilder( TesseractOCR::class )->disableOriginalConstructor()->getMock();
				$tesseractOCR->method( 'availableLanguages' )
					->will( $this->returnValue( [ 'eng', 'spa', 'tha', 'tir' ] ) );
				$tesseractEngine = new TesseractEngine(
					new MockHttpClient(),
					$intuition,
					$this->projectDir,
					$tesseractOCR
				);
				$engine = $tesseractEngine;
				break;

			case 'transkribus':
				$transkribusEngine = new TranskribusEngine(
					new TranskribusClient(
						getenv( 'APP_TRANSKRIBUS_USERNAME' ),
						getenv( 'APP_TRANSKRIBUS_PASSWORD' ),
						new MockHttpClient(),
						new NullAdapter(),
						new NullAdapter()
					),
					$intuition,
					$this->projectDir,
					new MockHttpClient()
				);
				$engine = $transkribusEngine;
				break;

			default:
				$googleEngine = new GoogleCloudVisionEngine(
					dirname( __DIR__ ) . '/fixtures/google-account-keyfile.json',
					$intuition,
					$this->projectDir,
					new MockHttpClient()
				);
				$engine = $googleEngine;
				break;
		}
		return $engine;
	}
}
