<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests\Fixtures;

/**
 * JSON VO с nullable-параметром без значения по умолчанию.
 */
final readonly class JsonConfigWithNullable
{
    public function __construct(
        public int $a,
        public ?string $comment,
    ) {
    }
}
