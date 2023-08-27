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
use Generator;
use Krinkle\Intuition\Intuition;
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
		$intuition = new Intuition();
		$this->googleEngine = new GoogleCloudVisionEngine(
			dirname( __DIR__ ) . '/fixtures/google-account-keyfile.json',
			$intuition,
			$this->projectDir,
			new MockHttpClient()
		);

		$tesseractOCR = $this->getMockBuilder( TesseractOCR::class )->disableOriginalConstructor()->getMock();
		$tesseractOCR->method( 'availableLanguages' )
			->will( $this->returnValue( [ 'eng', 'spa', 'tha', 'tir' ] ) );
		$this->tesseractEngine = new TesseractEngine(
			new MockHttpClient(),
			$intuition,
			$this->projectDir,
			$tesseractOCR
		);

		$this->transkribusEngine = new TranskribusEngine(
			new TranskribusClient(
				getenv( 'APP_TRANSKRIBUS_ACCESS_TOKEN' ),
				getenv( 'APP_TRANSKRIBUS_REFRESH_TOKEN' ),
				new MockHttpClient()
			),
			$intuition,
			$this->projectDir,
			new MockHttpClient()
		);
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
	 * @param string[] $langs Language codes
	 * @param string[] $validLangs
	 * @param string $invalidLangsMode
	 * @covers EngineBase::filterValidLangs for Tesseract Engine
	 * @dataProvider provideTesseractLangs
	 */
	public function testFilterValidLangsTesseractEngine(
		array $langs, array $validLangs, string $invalidLangsMode
	): void {
		if ( EngineBase::WARN_ON_INVALID_LANGS === $invalidLangsMode ) {
			$this->assertSame( $validLangs, $this->tesseractEngine->filterValidLangs( $langs, $invalidLangsMode )[0] );
		} else {
			if ( $langs !== $validLangs ) {
				$this->expectException( OcrException::class );
			}
			$this->tesseractEngine->filterValidLangs( $langs, $invalidLangsMode );
			$this->addToAssertionCount( 1 );
		}
	}

	/**
	 * @param string[] $langs Language codes
	 * @param string[] $validLangs
	 * @param string $invalidLangsMode
	 * @covers EngineBase::filterValidLangs for Transkribus Engine
	 * @dataProvider provideTranskribusLangs
	 */
	public function testFilterValidLangsTranskribusEngine(
		array $langs, array $validLangs, string $invalidLangsMode
	): void {
		if ( EngineBase::WARN_ON_INVALID_LANGS === $invalidLangsMode ) {
			$this->assertSame( $validLangs, $this->transkribusEngine->filterValidLangs( $langs, $invalidLangsMode )[0] );
		} else {
			if ( $langs !== $validLangs ) {
				$this->expectException( OcrException::class );
			}
			$this->transkribusEngine->filterValidLangs( $langs, $invalidLangsMode );
			$this->addToAssertionCount( 1 );
		}

	}

	/**
	 * @return Generator
	 */
	public function provideTesseractLangs(): Generator {
		// Format is [ [ langs to test ], [ subset of valid languages ] ]
		$baseCases = [
			'all valid' => [ [ 'en', 'fr' ], [ 'en', 'fr' ] ],
			'one invalid' => [ [ 'foo', 'fr' ], [ 'fr' ] ],
			// 'equ' is excluded on purpose: T284827
			'intentionally excluded' => [ [ 'equ' ], [] ],
		];
		foreach ( $baseCases as $name => $params ) {
			yield $name . ', no exception' => array_merge( $params, [ EngineBase::WARN_ON_INVALID_LANGS ] );
			yield $name . ', throw exception' => array_merge( $params, [ EngineBase::ERROR_ON_INVALID_LANGS ] );
		}
	}

	/**
	 * @return Generator
	 */
	public function provideTranskribusLangs(): Generator {
		// Format is [ [ langs to test ], [ subset of valid languages ] ]
		$baseCases = [
			'all valid' => [ [ 'en-b2022', 'fr-m1' ], [ 'en-b2022', 'fr-m1' ] ],
			'one invalid' => [ [ 'foo', 'fr-m1' ], [ 'fr-m1' ] ],
			// 'equ' is excluded on purpose: T284827
			'intentionally excluded' => [ [ 'equ' ], [] ],
		];
		foreach ( $baseCases as $name => $params ) {
			yield $name . ', no exception' => array_merge( $params, [ EngineBase::WARN_ON_INVALID_LANGS ] );
			yield $name . ', throw exception' => array_merge( $params, [ EngineBase::ERROR_ON_INVALID_LANGS ] );
		}
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
	}
}
