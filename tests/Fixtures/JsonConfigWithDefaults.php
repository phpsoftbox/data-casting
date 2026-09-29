<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests\Fixtures;

/**
 * JSON VO, в который добавили поля с дефолтами уже после появления сохранённых данных.
 */
final readonly class JsonConfigWithDefaults
{
    public function __construct(
        public int $a,
        public bool $enabled = true,
        public ?string $comment = 'default comment',
        public ?string $note = null,
    ) {
    }
}
