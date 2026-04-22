<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Handlers;

use InvalidArgumentException;

use function is_int;
use function is_numeric;
use function is_string;
use function ltrim;
use function preg_match;
use function strcmp;
use function strlen;

use const PHP_INT_MAX;
use const PHP_INT_MIN;

final class IntHandler extends AbstractTypeHandler
{
    public function supports(string $type): bool
    {
        return $type === 'int'
            || $type === 'integer'
            || $type === 'bigint'
            || $type === 'bigInteger';
    }

    public function castFrom(mixed $value, array $options = []): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            if ($this->isIntegerStringOutOfPhpIntRange($value)) {
                throw new InvalidArgumentException('Cannot cast value to int: value is out of PHP int range');
            }

            return (int) $value;
        }

        throw new InvalidArgumentException('Cannot cast value to int');
    }

    private function isIntegerStringOutOfPhpIntRange(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        if (preg_match('/^[+-]?\d+$/', $value) !== 1) {
            return false;
        }

        $negative = $value[0] === '-';
        $digits   = ltrim(ltrim($value, '+-'), '0');
        if ($digits === '') {
            return false;
        }

        $limit = $negative
            ? ltrim((string) PHP_INT_MIN, '-')
            : (string) PHP_INT_MAX;

        if (strlen($digits) < strlen($limit)) {
            return false;
        }

        if (strlen($digits) > strlen($limit)) {
            return true;
        }

        return strcmp($digits, $limit) > 0;
    }
}
