<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Handlers;

use InvalidArgumentException;
use PhpSoftBox\DataCasting\Contracts\TypeHandlerInterface;

use function explode;
use function is_float;
use function is_int;
use function is_string;
use function ltrim;
use function max;
use function number_format;
use function preg_match;
use function rtrim;
use function str_contains;
use function str_pad;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

use const STR_PAD_LEFT;
use const STR_PAD_RIGHT;

/**
 * Money handler.
 *
 * БД: хранит сумму в копейках (int).
 * PHP: возвращает нормализованную строку с фиксированным scale.
 */
final class MoneyHandler implements TypeHandlerInterface
{
    public function supports(string $type): bool
    {
        return $type === 'money';
    }

    public function castTo(mixed $value, array $options = []): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $scale = $this->normalizeScale($options);

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            $value = number_format($value, $scale, '.', '');
        }

        if (is_string($value)) {
            return $this->majorToMinor($value, $scale);
        }

        throw new InvalidArgumentException('Invalid money value.');
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $scale             = $this->normalizeScale($options);
        $trimTrailingZeros = (bool) ($options['trim_trailing_zeros'] ?? false);

        if (is_float($value)) {
            $value = (int) $value;
        }

        $minor = $this->normalizeMinor($value);
        $major = $this->minorToMajor($minor, $scale);

        if ($trimTrailingZeros && $scale > 0 && str_contains($major, '.')) {
            $major = rtrim(rtrim($major, '0'), '.');
        }

        return $major;
    }

    private function normalizeScale(array $options): int
    {
        $scale = (int) ($options['scale'] ?? 2);

        return max($scale, 0);
    }

    private function normalizeMinor(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return '0';
            }

            if (!preg_match('/^-?\d+$/', $value)) {
                throw new InvalidArgumentException('Invalid money value.');
            }

            return $value;
        }

        throw new InvalidArgumentException('Invalid money value.');
    }

    private function minorToMajor(string $minor, int $scale): string
    {
        $negative = str_starts_with($minor, '-');
        if ($negative) {
            $minor = substr($minor, 1);
        }

        $minor = ltrim($minor, '0');
        if ($minor === '') {
            $minor = '0';
        }

        if ($scale === 0) {
            $major = $minor;
        } elseif (strlen($minor) <= $scale) {
            $major = '0.' . str_pad($minor, $scale, '0', STR_PAD_LEFT);
        } else {
            $major = substr($minor, 0, -$scale) . '.' . substr($minor, -$scale);
        }

        if ($negative && $minor !== '0') {
            $major = '-' . $major;
        }

        return $major;
    }

    private function majorToMinor(string $major, int $scale): int|string
    {
        $major = trim($major);
        if ($major === '') {
            return 0;
        }

        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $major)) {
            throw new InvalidArgumentException('Invalid money value.');
        }

        $negative = str_starts_with($major, '-');
        if ($negative) {
            $major = substr($major, 1);
        }

        $parts    = explode('.', $major, 2);
        $whole    = $parts[0] ?? '0';
        $fraction = $parts[1] ?? '';

        if ($scale === 0 && $fraction !== '') {
            throw new InvalidArgumentException('Invalid money value.');
        }

        if (strlen($fraction) > $scale) {
            throw new InvalidArgumentException('Too many decimal places for money value.');
        }

        $fraction = str_pad($fraction, $scale, '0', STR_PAD_RIGHT);

        $whole = ltrim($whole, '0');
        if ($whole === '') {
            $whole = '0';
        }

        $minor = ltrim($whole . $fraction, '0');
        if ($minor === '') {
            $minor = '0';
        }

        if ($negative && $minor !== '0') {
            $minor = '-' . $minor;
        }

        return (int) $minor;
    }
}
