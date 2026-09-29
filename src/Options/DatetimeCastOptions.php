<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Options;

use DateTimeInterface;

/**
 * Опции date/time-кастинга.
 *
 * Незаданные (null) поля не попадают в итоговые опции: вместо них используются дефолты,
 * зарегистрированные в TypeCastOptionsManager, а затем дефолты DateTimeHandler
 * (в том числе класс, переданный в DefaultTypeCasterFactory).
 */
final readonly class DatetimeCastOptions implements TypeCastingOptionsInterface
{
    /**
     * @param string|null $formatTo Формат даты для преобразования.
     * @param string|null $formatFrom Формат даты для обратного преобразования.
     * @param class-string<DateTimeInterface>|null $dateTimeClass
     */
    public function __construct(
        public ?string $formatTo = null,
        public ?string $formatFrom = null,
        public ?string $dateTimeClass = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'format_to'     => $this->formatTo,
            'format_from'   => $this->formatFrom,
            'dateTimeClass' => $this->dateTimeClass,
        ];
    }
}
