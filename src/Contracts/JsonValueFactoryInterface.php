<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Contracts;

use PhpSoftBox\DataCasting\JsonHydrationContext;

interface JsonValueFactoryInterface
{
    /**
     * @param array<string, mixed> $data Декодированные данные текущего JSON-значения.
     *
     * JsonHydrationContext::source может содержать исходную структуру до приведения типов.
     * Фабрика не должна считать типы её значений типами свойств гидратируемого объекта.
     */
    public function create(array $data, JsonHydrationContext $context): object;
}
