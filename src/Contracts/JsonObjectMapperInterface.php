<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Contracts;

use PhpSoftBox\DataCasting\JsonHydrationContext;
use stdClass;

interface JsonObjectMapperInterface
{
    /**
     * @param class-string $class
     */
    public function hydrateObject(
        string $class,
        mixed $data,
        ?JsonHydrationContext $context = null,
        ?string $factoryClass = null,
    ): object;

    /**
     * @param class-string $itemClass
     * @return list<object>
     */
    public function hydrateCollection(
        string $itemClass,
        mixed $data,
        ?JsonHydrationContext $context = null,
        ?string $factoryClass = null,
    ): array;

    /**
     * @param class-string $valueClass
     * @return array<array-key, object>
     */
    public function hydrateMap(
        string $valueClass,
        mixed $data,
        ?JsonHydrationContext $context = null,
        ?string $factoryClass = null,
    ): array;

    /**
     * @param class-string $class
     */
    public function normalizeObject(object $value, string $class, string $path = '$'): stdClass;

    /**
     * @param class-string $itemClass
     * @return list<mixed>
     */
    public function normalizeCollection(array $value, string $itemClass, string $path = '$'): array;

    /**
     * @param class-string $valueClass
     */
    public function normalizeMap(array $value, string $valueClass, string $path = '$'): stdClass;
}
