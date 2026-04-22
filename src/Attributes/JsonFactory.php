<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Attributes;

use Attribute;
use PhpSoftBox\DataCasting\Contracts\JsonValueFactoryInterface;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
final readonly class JsonFactory
{
    /**
     * @param class-string<JsonValueFactoryInterface> $factory
     */
    public function __construct(
        public string $factory,
    ) {
    }
}
