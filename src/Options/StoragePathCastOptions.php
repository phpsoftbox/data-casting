<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Options;

use PhpSoftBox\Storage\Storage;

use function array_filter;

final readonly class StoragePathCastOptions implements TypeCastingOptionsInterface
{
    public function __construct(
        public ?string $disk = null,
        public ?Storage $storage = null,
    ) {
    }

    public function toArray(): array
    {
        return array_filter([
            'disk'    => $this->disk,
            'storage' => $this->storage,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
