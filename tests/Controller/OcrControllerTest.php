<?php
declare(strict_types = 1);

namespace App\Tests\Controller;

use App\Controller\OcrController;
use App\Engine\EngineFactory;
use App\Engine\GoogleCloudVisionEngine;
use App\Engine\TesseractEngine;
use Krinkle\Intuition\Intuition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use thiagoalessio\TesseractOCR\TesseractOCR;

class OcrControllerTest extends TestCase
{

    /**
     * @dataProvider provideGetLang
     * @param string[] $getParams
     * @param string[] $expectedLangs
     */
    public function testGetLang(array $getParams, array $expectedLangs): void
    {
        $request = new Request($getParams);
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $intuition = new Intuition([]);
        $gcv = new GoogleCloudVisionEngine(dirname(__DIR__).'/fixtures/google-account-keyfile.json', $intuition);
        $controller = new OcrController(
            $requestStack,
            $intuition,
            new EngineFactory($gcv, new TesseractEngine(new MockHttpClient(), $intuition, new TesseractOCR())),
            new FilesystemAdapter()
        );
        $this->assertSame($expectedLangs, $controller->getLangs($request));
    }

    /**
     * @return mixed[]
     */
    public function provideGetLang(): array
    {
        return [
            [
                ['lang' => 'ar'],
                ['ar'],
            ],
            [
                ['langs' => ['a|b', 'c!', 'ab']],
                ['ab', 'c'],
            ],
        ];
    }
}
