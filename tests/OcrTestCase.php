<?php
declare(strict_types = 1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class OcrTestCase extends KernelTestCase
{
    /** @var string */
    protected $projectDir;

    /** @var string */
    protected $accessToken;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->projectDir = self::$kernel->getProjectDir();
        $this->accessToken = "eyJhbGciOiJSUzI1NiIsInR5cC";
    }
}
