<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\EngineNotFoundException;

class EngineFactory
{

    /** @var array<string, EngineBase> */
    private $engines;

    public function __construct(
        GoogleCloudVisionEngine $cloudVisionEngine, 
        TesseractEngine $tesseractEngine, 
        TranskribusEngine $transkribusEngine
    ){
        $this->engines = [
            'google' => $cloudVisionEngine,
            'tesseract' => $tesseractEngine,
            'transkribus' => $transkribusEngine,
        ];
    }

    public function get(string $name): EngineBase
    {
        if (!isset($this->engines[$name])) {
            throw new EngineNotFoundException();
        }
        return $this->engines[$name];
    }
}
