<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Options;

/**
 * Опции bool-кастинга.
 *
 * Незаданные (null) поля не попадают в итоговые опции: вместо них используются дефолты,
 * зарегистрированные в TypeCastOptionsManager, а затем дефолты BooleanHandler.
 */
final readonly class BoolCastOptions implements TypeCastingOptionsInterface
{
    /**
     * @param list<int|string|bool>|null $trueValues Значения, считающиеся true
     *                                               (по умолчанию: true, 1, '1', 'true', 't', 'yes', 'y', 'on').
     * @param list<int|string|bool>|null $falseValues Значения, считающиеся false
     *                                                (по умолчанию: false, 0, '0', 'false', 'f', 'no', 'n', 'off', '').
     * @param bool|null $strict Бросать исключение для нераспознанного значения (по умолчанию false).
     */
    public function __construct(
        public ?array $trueValues = null,
        public ?array $falseValues = null,
        public ?bool $strict = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'true_values'  => $this->trueValues,
            'false_values' => $this->falseValues,
            'strict'       => $this->strict,
        ];
    }
}
