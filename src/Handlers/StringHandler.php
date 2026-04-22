<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Handlers;

use function in_array;

final class StringHandler extends AbstractTypeHandler
{
    public function supports(string $type): bool
    {
        return in_array($type, ['string', 'text', 'tinytext', 'mediumtext', 'longtext'], true);
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    public function castTo(mixed $value, array $options = []): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
