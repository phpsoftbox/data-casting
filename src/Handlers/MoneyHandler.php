<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Handlers;

use InvalidArgumentException;
use PhpSoftBox\DataCasting\Contracts\TypeHandlerInterface;

use function explode;
use function floor;
use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function ltrim;
use function max;
use function preg_match;
use function rtrim;
use function str_contains;
use function str_pad;
use function str_starts_with;
use function strcmp;
use function strlen;
use function substr;
use function trim;

use const PHP_INT_MAX;
use const PHP_INT_MIN;
use const STR_PAD_LEFT;
use const STR_PAD_RIGHT;

/**
 * Money handler.
 *
 * БД: сумма хранится целым числом в минорных единицах (копейках) — int или строка из цифр.
 * PHP: сумма в мажорных единицах (рублях) — нормализованная строка с фиксированным scale.
 *
 * Семантика не зависит от PHP-типа значения, а определяется только направлением:
 *  - castTo() (PHP → БД) принимает мажорные единицы: `100`, `'100'` и `100.0` — это 100 рублей → 10000;
 *  - castFrom() (БД → PHP) принимает минорные единицы: `10000` и `'10000'` → `'100.00'`.
 *
 * float в castTo() допускается, только если его кратчайшее десятичное представление укладывается
 * в scale (`12.34` → 1234, а `0.1 + 0.2` или `12.345` → исключение). В castFrom() float допускается,
 * только если он целый. Значение вне диапазона PHP int → исключение.
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
            return $this->majorToMinor((string) $value, $scale);
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new InvalidArgumentException('Invalid money value: non-finite float.');
            }

            // Кратчайшее точное представление без экспоненты; лишние знаки дадут исключение ниже.
            $value = (string) new DecimalHandler()->castTo($value);
        }

        if (is_string($value)) {
            return $this->majorToMinor($value, $scale);
        }

        throw new InvalidArgumentException('Invalid money value.');
    }

    public function castFrom(mixed $value, array $options = []): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $scale             = $this->normalizeScale($options);
        $trimTrailingZeros = (bool) ($options['trim_trailing_zeros'] ?? false);

        if (is_float($value)) {
            if (!is_finite($value) || floor($value) !== $value) {
                throw new InvalidArgumentException('Invalid money value: minor units must be an integer.');
            }

            $value = (string) new DecimalHandler()->castTo($value);
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
                throw new InvalidArgumentException('Invalid money value: minor units must be an integer.');
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

    private function majorToMinor(string $major, int $scale): int
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

        // Хвостовые нули за пределами scale допустимы (`'12.340'` при scale 2), значащие — нет.
        if (strlen(rtrim($fraction, '0')) > $scale) {
            throw new InvalidArgumentException('Too many decimal places for money value.');
        }

        $fraction = substr(str_pad($fraction, $scale, '0', STR_PAD_RIGHT), 0, $scale);

        $whole = ltrim($whole, '0');
        if ($whole === '') {
            $whole = '0';
        }

        $minor = ltrim($whole . $fraction, '0');
        if ($minor === '') {
            $minor = '0';
        }

        $limit = $negative ? ltrim((string) PHP_INT_MIN, '-') : (string) PHP_INT_MAX;
        if (strlen($minor) > strlen($limit) || (strlen($minor) === strlen($limit) && strcmp($minor, $limit) > 0)) {
            throw new InvalidArgumentException('Money value is out of PHP int range.');
        }

        if ($negative && $minor !== '0') {
            $minor = '-' . $minor;
        }

        return (int) $minor;
    }
}
