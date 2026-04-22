<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Contracts;

interface JsonHydratableInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public static function fromJsonData(array $data): static;
}
