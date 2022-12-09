<?php
declare(strict_types = 1);

namespace App\Engine;

use App\Exception\OcrException;
use Imagine\Gd\Imagine;
use Krinkle\Intuition\Intuition;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class EngineBase
{

    public const ALLOWED_FORMATS = ['png', 'jpeg', 'jpg', 'gif', 'tiff', 'tif', 'webp'];

    public const WARN_ON_INVALID_LANGS = 'warn';
    public const ERROR_ON_INVALID_LANGS = 'error';

    /** @const Download the image data to the web server. */
    public const DO_DOWNLOAD_IMAGE = true;

    /** @var string[] The host names for the images. */
    protected $imageHosts = [];

    /** @var Intuition */
    protected $intuition;

    /** @var string */
    protected $projectDir;

    /** @var HttpClientInterface */
    private $httpClient;

    /** @var string[][] Local PHP array copy of langs.json */
    protected $langList;

    /** @var string[] Additional localized names for non-standard language codes. */
    public const LANG_NAMES = [
        'az-cyrl' => 'Azərbaycan (qədim yazı)',
        'de-frk' => 'Deutsch (Fraktur)',
        'enm' => 'Middle English (1100-1500)',
        'es-old' => 'español (viejo)',
        'frm' => 'moyen français (1400-1600)',
        'fro' => 'Franceis, François, Romanz (1400-1600)',
        'it-old' => 'italiano antico',
        'ka-old' => 'ქართული (ძველი)',
        'ko-vert' => '한국어 (세로)',
        'kur' => 'کوردی',
        'osd' => 'Orientation and script detection module',
        'ru-petr1708' => 'Русский (старая орфография)',
        'sr-latn' => 'Српски (латиница)',
        'syr' => 'leššānā Suryāyā',
        'uz-cyrl' => 'oʻzbekcha',
    ];

    /**
     * EngineBase constructor.
     * @param Intuition $intuition
     * @param string $projectDir
     */
    public function __construct(Intuition $intuition, string $projectDir, HttpClientInterface $httpClient)
    {
        $this->intuition = $intuition;
        $this->projectDir = $projectDir;
        $this->httpClient = $httpClient;
    }

    /**
     * Unique identifier for the engine.
     * @return string
     */
    abstract public static function getId(): string;

    /**
     * Get transcribed text from the given image.
     * @param string $imageUrl
     * @param string $invalidLangsMode
     * @param int[] $crop
     * @param string[]|null $langs
     * @return EngineResult
     */
    abstract public function getResult(
        string $imageUrl,
        string $invalidLangsMode,
        array $crop,
        ?array $langs = null
    ): EngineResult;

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
        $regex = "/(https?:)?\/\/($hostRegex)\/.+($formatRegex)$/";
        $matches = preg_match($regex, strtolower($imageUrl));
        if (1 !== $matches) {
            $params = [count($this->getImageHosts()), $this->intuition->listToText($this->getImageHosts())];
            throw new OcrException('image-url-error', $params);
        }
    }

    /**
     * @param string[]|null $langs
     * @param string $invalidLangsMode
     * @return string[][] [ valid languages in $langs, invalid languages in $langs ]
     * @throws OcrException If there are invalid languages and $invalidLangsMode is self::ERROR_ON_INVALID_LANGS
     */
    public function filterValidLangs(?array $langs, string $invalidLangsMode): array
    {
        $invalidLangs = array_values(array_diff($langs, $this->getValidLangs()));
        if (!$invalidLangs) {
            return [ $langs, [] ];
        }

        if (self::WARN_ON_INVALID_LANGS === $invalidLangsMode) {
            return [ array_values(array_diff($langs, $invalidLangs)), $invalidLangs ];
        }

        throw new OcrException('langs-param-error', [
            count($invalidLangs),
            $this->intuition->listToText($invalidLangs),
        ]);
    }

    /**
     * @param string[] $invalidLangs
     * @return string
     */
    protected function getInvalidLangsWarning(array $invalidLangs): string
    {
        return $this->intuition->msg(
            'engine-invalid-langs-warning',
            [ 'variables' => [ $this->intuition->listToText($invalidLangs) ] ]
        );
    }

    /**
     * @param string $imageUrl The original image URL.
     * @param int[] $crop Array with keys `x, `y`, `width` and `height`.
     * @param ?bool $downloadMode Whether to download the image or not.
     * @return Image
     * @throws OcrException If the image couldn't be fetched.
     */
    public function getImage(string $imageUrl, array $crop, ?bool $downloadMode = false): Image
    {
        $image = new Image($imageUrl, $crop);

        if (self::DO_DOWNLOAD_IMAGE !== $downloadMode && !$image->needsCropping()) {
            return $image;
        }

        $imageResponse = $this->httpClient->request('GET', $image->getUrl());
        try {
            $data = $imageResponse->getContent();
        } catch (ClientException $exception) {
            throw new OcrException('image-retrieval-failed', [$exception->getMessage()]);
        }

        if (!$image->needsCropping()) {
            // If it doesn't need cropping, use the full image's data.
            $image->setData($data);
            $image->setSize((int) $imageResponse->getHeaders()['content-length'][0]);
        } else {
            // Otherwise, crop it.
            $imagine = new Imagine();
            $loadedImage = $imagine->load($data);
            $croppedImage = $image->getCrop()->apply($loadedImage);
            $image->setData($croppedImage->get('jpg'));
        }

        return $image;
    }
}
