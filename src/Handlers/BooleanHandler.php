<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Handlers;

use InvalidArgumentException;
use PhpSoftBox\DataCasting\Contracts\TypeHandlerInterface;

use function in_array;
use function is_bool;

/**
 * Bool для ORM: понимает разные представления boolean из БД (0/1, t/f, yes/no).
 */
final class BooleanHandler implements TypeHandlerInterface
{
    public function supports(string $type): bool
    {
        return $type === 'bool' || $type === 'boolean';
    }

    public function castTo(mixed $value, array $options = []): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $trueValues  = $options['true_values'] ?? [true, 1, '1', 'true', 't', 'yes', 'y', 'on'];
        $falseValues = $options['false_values'] ?? [false, 0, '0', 'false', 'f', 'no', 'n', 'off', ''];
        $strict      = (bool) ($options['strict'] ?? false);

        if (in_array($value, $trueValues, true)) {
            return true;
        }

        if (in_array($value, $falseValues, true)) {
            return false;
        }

        if ($strict) {
            throw new InvalidArgumentException('Invalid boolean value.');
        }

        return (bool) $value;
    }

    public function cast(mixed $value): mixed
    {
        return $this->castFrom($value);
    }
}
