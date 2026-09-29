<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Options;

/**
 * Опции pg_array-кастинга.
 *
 * Незаданные (null) поля не попадают в итоговые опции.
 */
final readonly class PgArrayCastOptions implements TypeCastingOptionsInterface
{
    /**
     * @param 'string'|'int'|'float'|'bool'|'uuid'|'datetime'|null $itemType Тип элементов при чтении
     *                                                                       (по умолчанию элементы остаются строками).
     * @param bool|null $emptyStringAsEmptyArray Пустая строка из БД — пустой массив, иначе null (по умолчанию true).
     */
    public function __construct(
        public ?string $itemType = null,
        public ?bool $emptyStringAsEmptyArray = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'item_type'                   => $this->itemType,
            'empty_string_as_empty_array' => $this->emptyStringAsEmptyArray,
        ];
    }
}
