<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class JsonMapOf
{
    /**
     * @param class-string $valueClass
     */
    public function __construct(
        public string $valueClass,
    ) {
    }
}
