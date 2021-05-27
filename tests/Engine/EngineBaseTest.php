<?php
declare(strict_types = 1);

namespace App\Tests\Engine;

use App\Engine\GoogleCloudVisionEngine;
use App\Engine\TesseractEngine;
use App\Exception\OcrException;
use App\Tests\OcrTestCase;
use Krinkle\Intuition\Intuition;
use Symfony\Component\HttpClient\MockHttpClient;
use thiagoalessio\TesseractOCR\TesseractOCR;

class EngineBaseTest extends OcrTestCase
{
    /** @var GoogleCloudVisionEngine */
    private $googleEngine;

    /** @var TesseractEngine */
    private $tesseractEngine;

    public function setUp(): void
    {
        parent::setUp();
        $intuition = new Intuition();
        $this->googleEngine = new GoogleCloudVisionEngine(
            dirname(__DIR__).'/fixtures/google-account-keyfile.json',
            $intuition,
            $this->projectDir
        );

        $tesseractOCR = $this->getMockBuilder(TesseractOCR::class)->disableOriginalConstructor()->getMock();
        $tesseractOCR->method('availableLanguages')
            ->will($this->returnValue(['eng', 'spa', 'tha', 'tir']));
        $this->tesseractEngine = new TesseractEngine(
            new MockHttpClient(),
            $intuition,
            $this->projectDir,
            $tesseractOCR
        );
    }

    /**
     * @covers EngineBase::checkImageUrl
     * @dataProvider provideCheckImageUrl()
     */
    public function testCheckImageUrl(string $url, bool $exceptionExpected): void
    {
        $this->tesseractEngine->setImageHosts('upload.wikimedia.org,localhost');
        if ($exceptionExpected) {
            static::expectException(OcrException::class);
        }
        $this->tesseractEngine->checkImageUrl($url);
        static::assertTrue(true);
    }

    /**
     * @return mixed[][]
     */
    public function provideCheckImageUrl(): array
    {
        return [
            // Pass:
            ['https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg', false],
            ['https://upload.wikimedia.org/wikipedia/commons/file.jpg', false],
            // Fail:
            ['https://foo.example.com/wikipedia/commons/a/a9/Example.jpg', true],
            ['https://en.wikisource.org/file.mov', true],
            ['https://localhosts/file.jpg', true],
        ];
    }

    /**
     * @covers EngineBase::validateLangs for Google Engine
     */
    public function testValidateLangsGoogleEngine(): void
    {
        // Should not throw an exception.
        $this->googleEngine->validateLangs(['en', 'es']);

        // Should throw an exception. 'sp` is not a valid lang
        static::expectException(OcrException::class);
        $this->googleEngine->validateLangs(['en', 'sp']);
    }

    /**
     * @covers EngineBase::validateLangs for Tesseract Engine
     */
    public function testValidateLangsTesseractEngine(): void
    {
        // Should not throw an exception.
        $this->tesseractEngine->validateLangs(['en', 'fr']);

        // Should throw an exception. `foo` is not a valid lang
        static::expectException(OcrException::class);
        $this->tesseractEngine->validateLangs(['foo', 'fr']);
    }

    /**
     * @covers EngineBase::getLangCodes
     */
    public function testLangCodes(): void
    {
        static::assertSame(['eng', 'fra'], $this->tesseractEngine->getLangCodes(['en', 'fr']));
        static::assertSame(['en', 'iw'], $this->googleEngine->getLangCodes(['en', 'he']));
    }
}
