<?php
declare(strict_types = 1);

namespace App\Engine;

use Exception;

class EngineFactory
{

    /** @var array<string, EngineBase> */
    private $engines;

    public function __construct(GoogleCloudVisionEngine $cloudVisionEngine, TesseractEngine $tesseractEngine)
    {
        $this->engines = [
            'google' => $cloudVisionEngine,
            'tesseract' => $tesseractEngine,
        ];
    }

    public function get(string $name): EngineBase
    {
        if (!isset($this->engines[$name])) {
            throw new Exception('Engine not found: '.$name);
        }
        return $this->engines[$name];
    }
}
