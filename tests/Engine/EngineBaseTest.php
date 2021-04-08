<?php
declare(strict_types = 1);

namespace App\Tests\Engine;

use App\Engine\GoogleCloudVisionEngine;
use App\Exception\OcrException;
use PHPUnit\Framework\TestCase;

class EngineBaseTest extends TestCase
{
    /**
     * @covers EngineBase::checkImageUrl
     */
    public function testCheckImageUrl(): void
    {
        $engine = new GoogleCloudVisionEngine('foo', 'bar');

        // Should not throw an exception.
        $engine->checkImageUrl('https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg');

        // Should throw an exception.
        static::expectException(OcrException::class);
        $engine->checkImageUrl('https://upload.wikimedia.org/wikipedia/commons');
    }
}
