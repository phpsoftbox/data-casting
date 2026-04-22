<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Options;

final readonly class MoneyCastOptions implements TypeCastingOptionsInterface
{
    public function __construct(
        public int $scale = 2,
        public bool $trimTrailingZeros = false,
    ) {
    }

    public function toArray(): array
    {
        return [
            'scale'               => $this->scale,
            'trim_trailing_zeros' => $this->trimTrailingZeros,
        ];
    }
}
