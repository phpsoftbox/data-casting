<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Options;

/**
 * Опции decimal-кастинга.
 *
 * Незаданные (null) поля не попадают в итоговые опции.
 */
final readonly class DecimalCastOptions implements TypeCastingOptionsInterface
{
    /**
     * @param int|null $scale Число знаков после точки: значение дополняется нулями до scale,
     *                        лишние значащие знаки приводят к исключению.
     * @param bool|null $trimTrailingZeros Убирать хвостовые нули дробной части (по умолчанию false).
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
