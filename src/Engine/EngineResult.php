<?php
declare(strict_types = 1);

namespace App\Engine;

/**
 * Immutable and serializable value object that represents the result (output) of an OCR engine operation
 */
class EngineResult
{
    /** @var string */
    private $text;

    /** @var string[] */
    private $warnings;

    /**
     * @param string $text
     * @param string[] $warnings
     */
    public function __construct(string $text, array $warnings = [])
    {
        $this->text = $text;
        $this->warnings = $warnings;
    }

    /**
     * @return string
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @return string[]
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }
}
