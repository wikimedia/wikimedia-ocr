<?php
declare(strict_types = 1);

namespace App\Exception;

use Exception;

class OcrException extends Exception
{
    /** @var string */
    protected $i18nKey;

    /** @var mixed[] */
    protected $i18nParams;

    /**
     * OcrException constructor.
     * @param string $i18nKey
     * @param mixed[] $i18nParams
     */
    public function __construct(string $i18nKey, array $i18nParams = [])
    {
        parent::__construct();
        $this->i18nKey = $i18nKey;
        $this->i18nParams = $i18nParams;
    }

    /**
     * @return string
     */
    public function getI18nKey(): string
    {
        return $this->i18nKey;
    }

    /**
     * @return mixed[]
     */
    public function getI18nParams(): array
    {
        return $this->i18nParams;
    }
}
