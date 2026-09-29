<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Options;

/**
 * Реакция JsonHandler на невалидный JSON или JSON-скаляр вместо объекта/массива.
 */
enum JsonInvalidPolicy: string
{
    /**
     * Вернуть пустой массив. Опасно: при следующем сохранении сущности колонка будет перезаписана `[]`.
     * Для немаппленного JSON; для target/collection/map всё равно бросается исключение.
     */
    case Empty = 'empty';

    /**
     * Вернуть null. При следующем сохранении сущности колонка будет перезаписана NULL.
     */
    case Null = 'null';

    /**
     * Бросить InvalidArgumentException (по умолчанию).
     */
    case Throw = 'throw';
}
