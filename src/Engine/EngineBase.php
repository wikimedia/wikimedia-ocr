<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use Krinkle\Intuition\Intuition;

abstract class EngineBase
{

    public const ALLOWED_FORMATS = ['png', 'jpeg', 'jpg', 'gif', 'tiff', 'tif', 'webp'];

    /** @var string[] The host names for the images. */
    protected $imageHosts = [];

    /** @var Intuition */
    protected $intuition;

    /** @var string */
    protected $projectDir;

    /** @var string[][] Local PHP array copy of langs.json */
    protected $langList;

    /** @var string[] Additional localized names for non-standard language codes. */
    public const LANG_NAMES = [
        'az-cyrl' => 'Azərbaycan (qədim yazı)',
        'de-frk' => 'Deutsch (Fraktur)',
        'enm' => 'Middle English (1100-1500)',
        'es-old' => 'español (viejo)',
        'equ' => 'Math / equation detection module',
        'fro' => 'Franceis, François, Romanz (1400-1600)',
        'it-old' => 'italiano (vecchio)',
        'ka-old' => 'ქართული (ძველი)',
        'ko-vert' => '한국어 (세로)',
        'osd' => 'Orientation and script detection module',
        'sr-latn' => 'Српски (латиница)',
        'syr' => 'leššānā Suryāyā',
        'uz-cyrl' => 'oʻzbekcha',
    ];

    /**
     * EngineBase constructor.
     * @param Intuition $intuition
     * @param string $projectDir
     */
    public function __construct(Intuition $intuition, string $projectDir)
    {
        $this->intuition = $intuition;
        $this->projectDir = $projectDir;
    }

    /**
     * Unique identifier for the engine.
     * @return string
     */
    abstract public static function getId(): string;

    /**
     * Get transcribed text from the given image.
     * @param string $imageUrl
     * @param string[]|null $langs
     * @return string
     */
    abstract public function getText(string $imageUrl, ?array $langs = null): string;

    /**
     * Get the language list from langs.json
     * @return string[][]
     */
    private function getLangList(): array
    {
        if (!$this->langList) {
            $this->langList = json_decode(file_get_contents($this->projectDir.'/public/langs.json'), true);
        }

        return $this->langList;
    }

    /**
     * Get languages accepted by the engine.
     * @param bool $withNames Whether to include the localized language name.
     * @return string[] ISO 639-1 codes, optionally as keys with language names as the values.
     */
    public function getValidLangs(bool $withNames = false): array
    {
        $langs = array_keys(array_filter($this->getLangList(), function ($values) {
            return isset($values[static::getId()]);
        }));

        if (!$withNames) {
            return $langs;
        }

        // Add the localized names for each language.
        $list = [];
        foreach ($langs as $lang) {
            $list[$lang] = $this->getLangName($lang);
        }

        return $list;
    }

    /**
     * Get the name of the given language. This adds a few translations that don't exist in Intuition.
     * @param string|null $lang
     * @return string
     */
    public function getLangName(?string $lang = null): string
    {
        return '' === $this->intuition->getLangName($lang)
            ? (self::LANG_NAMES[$lang] ?? '')
            : $this->intuition->getLangName($lang);
    }

    /**
     * Transform the given ISO 639-1 codes into the language codes needed by this type of Engine.
     * @param string[] $langs
     * @return string[]
     */
    public function getLangCodes(array $langs): array
    {
        return array_map(function ($lang) {
            return $this->getLangList()[$lang][static::getId()];
        }, $langs);
    }

    /**
     * Set the allowed image hosts.
     * @param string $imageHosts
     */
    public function setImageHosts(string $imageHosts): void
    {
        $this->imageHosts = array_map('trim', explode(',', $imageHosts));
    }

    /**
     * Get the allowed image hosts.
     * @return string[]
     */
    public function getImageHosts(): array
    {
        return $this->imageHosts;
    }

    /**
     * Checks that the given image URL is valid.
     * @param string $imageUrl
     * @throws OcrException
     */
    public function checkImageUrl(string $imageUrl): void
    {
        $hostRegex = implode('|', array_map('preg_quote', $this->getImageHosts()));
        $formatRegex = implode('|', self::ALLOWED_FORMATS);
        $regex = "/https?:\/\/($hostRegex)\/.+($formatRegex)$/";
        $matches = preg_match($regex, strtolower($imageUrl));
        if (1 !== $matches) {
            $params = [count($this->getImageHosts()), $this->intuition->listToText($this->getImageHosts())];
            throw new OcrException('image-url-error', $params);
        }
    }

    /**
     * @param string[]|null $langs
     * @throws OcrException
     */
    public function validateLangs(?array $langs): void
    {
        $invalidLangs = array_diff($langs, $this->getValidLangs());

        if (count($invalidLangs)) {
            $invalidLangs = array_values($invalidLangs);
            throw new OcrException('langs-param-error', [
                count($invalidLangs),
                $this->intuition->listToText($invalidLangs),
            ]);
        }
    }
}
