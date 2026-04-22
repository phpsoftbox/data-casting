<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Options;

final readonly class PhoneCastOptions implements TypeCastingOptionsInterface
{
    public function __construct(
        public bool $withCountryCodeTo = false,
        public bool $withCountryCodeFrom = false,
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
