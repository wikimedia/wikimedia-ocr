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
        $engine->setImageHosts('upload.wikimedia.org, foo.example.com');

        // Should not throw an exception.
        $engine->checkImageUrl('https://upload.wikimedia.org/wikipedia/commons/a/a9/Example.jpg');
        $engine->checkImageUrl('https://foo.example.com/wikipedia/commons/a/a9/Example.jpg');

        // Should throw an exception.
        static::expectException(OcrException::class);
        $engine->checkImageUrl('https://upload.wikimedia.org/wikipedia/commons/file.jpg');
        $engine->checkImageUrl('https://en.wikisource.org/file.mov');
    }
}
