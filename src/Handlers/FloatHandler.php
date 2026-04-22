<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Handlers;

use InvalidArgumentException;

use function is_float;
use function is_numeric;

final class FloatHandler extends AbstractTypeHandler
{
    public function supports(string $type): bool
    {
        return $type === 'float' || $type === 'double';
    }

    public function castFrom(mixed $value, array $options = []): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_float($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        throw new InvalidArgumentException('Cannot cast value to float');
    }
}
