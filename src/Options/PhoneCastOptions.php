<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Options;

/**
 * Опции phone-кастинга.
 *
 * Незаданные (null) поля не попадают в итоговые опции.
 */
final readonly class PhoneCastOptions implements TypeCastingOptionsInterface
{
    /**
     * @param bool|null $withCountryCodeTo Сохранять номер с кодом страны (по умолчанию false).
     * @param bool|null $withCountryCodeFrom Возвращать номер с кодом страны (по умолчанию false).
     */
    public function __construct(
        public ?bool $withCountryCodeTo = null,
        public ?bool $withCountryCodeFrom = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'with_country_code_to'   => $this->withCountryCodeTo,
            'with_country_code_from' => $this->withCountryCodeFrom,
        ];
    }
}
