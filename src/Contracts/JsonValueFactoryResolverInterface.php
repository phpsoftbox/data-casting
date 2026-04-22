<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Contracts;

interface JsonValueFactoryResolverInterface
{
    /**
     * @param class-string<JsonValueFactoryInterface> $factoryClass
     */
    public function resolve(string $factoryClass): JsonValueFactoryInterface;
}
