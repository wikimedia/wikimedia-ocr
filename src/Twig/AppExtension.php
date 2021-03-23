<?php
declare(strict_types = 1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    /**
     * Registry of custom TwigFilters.
     * @return TwigFilter[]
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('textarea_rows', [$this, 'getTextareaRows']),
        ];
    }

    /**
     * Get the number of rows a textarea should be based on the size of the given text.
     * @param string $text
     * @return int
     */
    public function getTextareaRows(string $text): int
    {
        return max(10, substr_count($text, "\n"));
    }
}
