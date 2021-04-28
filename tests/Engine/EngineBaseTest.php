<?php
declare(strict_types = 1);

namespace App\Tests\Engine;

use App\Engine\GoogleCloudVisionEngine;
use App\Engine\TesseractEngine;
use App\Exception\OcrException;
use Krinkle\Intuition\Intuition;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use thiagoalessio\TesseractOCR\TesseractOCR;

class EngineBaseTest extends TestCase
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
            $intuition
        );

        $tesseractOCR = $this->getMockBuilder(TesseractOCR::class)->disableOriginalConstructor()->getMock();
        $tesseractOCR->method('availableLanguages')
            ->will($this->returnValue(['eng', 'spa', 'tha', 'tir']));
        $this->tesseractEngine = new TesseractEngine(new MockHttpClient(), $intuition, $tesseractOCR);
    }

    /**
     * @covers EngineBase::checkImageUrl
     */
    public function testCheckImageUrl(): void
    {
        // Should not throw an exception.
        $this->googleEngine->checkImageUrl('https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg');

        // Should throw an exception.
        static::expectException(OcrException::class);
        $this->googleEngine->checkImageUrl('https://upload.wikimedia.org/wikipedia/commons');
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
        $this->tesseractEngine->validateLangs(['eng', 'spa']);

        // Should throw an exception. `en` is not a valid lang
        static::expectException(OcrException::class);
        $this->tesseractEngine->validateLangs(['en', 'spa']);
    }
}
