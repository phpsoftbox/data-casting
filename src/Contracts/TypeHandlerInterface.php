<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Contracts;

interface TypeHandlerInterface
{
    /**
     * Может ли handler обработать данный тип.
     */
    public function supports(string $type): bool;

    /**
     * Преобразует значение в "скаляр" (то, что можно отправить в БД).
     */
    public function castTo(mixed $value, array $options = []): int|float|string|bool|null;

    /**
     * Преобразует значение из "скаляра" (пришло из БД) в PHP-тип.
     */
    public function castFrom(mixed $value, array $options = []): mixed;
}
