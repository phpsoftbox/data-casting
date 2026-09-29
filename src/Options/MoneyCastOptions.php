<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Options;

/**
 * Опции money-кастинга.
 *
 * Незаданные (null) поля не попадают в итоговые опции.
 */
final readonly class MoneyCastOptions implements TypeCastingOptionsInterface
{
    /**
     * @param int|null $scale Число знаков минорной единицы (по умолчанию 2: рубли/копейки).
     * @param bool|null $trimTrailingZeros Убирать хвостовые нули при чтении (по умолчанию false).
     */
    public function __construct(
        public ?int $scale = null,
        public ?bool $trimTrailingZeros = null,
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
