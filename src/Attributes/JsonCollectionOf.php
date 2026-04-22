<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class JsonCollectionOf
{
    /**
     * @param class-string $itemClass
     */
    public function __construct(
        public string $itemClass,
    ) {
    }
}
