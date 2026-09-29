<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting;

final readonly class JsonHydrationContext
{
    /**
     * @param array<string, mixed> $source Исходная строка/структура до приведения типов.
     *                                     Типы значений зависят от источника/драйвера и не должны
     *                                     восприниматься как типы свойств гидратируемого объекта.
     */
    public function __construct(
        public array $source = [],
        public ?string $ownerClass = null,
        public ?string $property = null,
        public string $path = '$',
    ) {
    }

    public function at(string $path): self
    {
        return new self(
            source: $this->source,
            ownerClass: $this->ownerClass,
            property: $this->property,
            path: $path,
        );
    }
}
