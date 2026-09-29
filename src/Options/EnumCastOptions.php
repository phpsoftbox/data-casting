<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Options;

use BackedEnum;

/**
 * Опции enum-кастинга.
 *
 * Незаданные (null) поля не попадают в итоговые опции.
 */
final readonly class EnumCastOptions implements TypeCastingOptionsInterface
{
    /**
     * @param class-string<BackedEnum> $enumClass
     * @param bool|null $nullOnInvalid Возвращать null для неизвестного значения (по умолчанию false).
     */
    public function __construct(
        public string $enumClass,
        public ?bool $nullOnInvalid = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'enum_class'      => $this->enumClass,
            'null_on_invalid' => $this->nullOnInvalid,
        ];
    }
}
