<?php
declare( strict_types = 1 );

namespace App\Tests\Controller;

use App\Controller\OcrController;
use App\Engine\EngineFactory;
use App\Engine\GoogleCloudVisionEngine;
use App\Engine\TesseractEngine;
use App\Engine\TranskribusClient;
use App\Engine\TranskribusEngine;
use App\Tests\OcrTestCase;
use Krinkle\Intuition\Intuition;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use thiagoalessio\TesseractOCR\TesseractOCR;

class OcrControllerTest extends OcrTestCase {

	/**
	 * @dataProvider provideGetLang
	 * @param string[] $getParams
	 * @param string[] $expectedLangs
	 */
	public function testGetLang( array $getParams, array $expectedLangs ): void {
		$request = new Request( $getParams );
		$requestStack = new RequestStack();
		$requestStack->push( $request );
		$request->setSession( new Session( new MockArraySessionStorage() ) );
		$intuition = new Intuition( [] );
		$gcv = new GoogleCloudVisionEngine(
			dirname( __DIR__ ) . '/fixtures/google-account-keyfile.json',
			$intuition,
			$this->projectDir,
			new MockHttpClient()
		);
		$controller = new OcrController(
			$requestStack,
			$intuition,
			new EngineFactory(
				$gcv,
				new TesseractEngine( new MockHttpClient(), $intuition, $this->projectDir, new TesseractOCR() ),
				new TranskribusEngine(
					new TranskribusClient(
						getenv( 'APP_TRANSKRIBUS_ACCESS_TOKEN' ),
						getenv( 'APP_TRANSKRIBUS_REFRESH_TOKEN' ),
						new MockHttpClient()
					),
					$intuition,
					$this->projectDir,
					new MockHttpClient()
				),
			),
			new FilesystemAdapter()
		);
		$this->assertSame( $expectedLangs, $controller->getLangs( $request ) );
	}

	/**
	 * @return mixed[]
	 */
	public function provideGetLang(): array {
		return [
			[
				[ 'lang' => 'ar' ],
				[ 'ar' ],
			],
			[
				[ 'langs' => [ 'a|b', 'c!', 'ab' ] ],
				[ 'ab', 'c' ],
			],
			'special characters' => [
				[ 'langs' => [ 'sr-Latn', 'Canadian_Aboriginal' ] ],
				[ 'sr-Latn', 'Canadian_Aboriginal' ],
			],
			'numbers' => [
				[ 'langs' => [ 'ru-petr1708' ] ],
				[ 'ru-petr1708' ],
			],
		];
	}
}
