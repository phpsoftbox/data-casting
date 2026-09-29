<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Handlers;

use InvalidArgumentException;
use PhpSoftBox\DataCasting\Contracts\TypeHandlerInterface;

use function is_finite;
use function is_float;
use function is_int;
use function is_string;
use function ltrim;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_pad;
use function str_repeat;
use function strlen;
use function strpos;
use function substr;
use function trim;

use const STR_PAD_RIGHT;

/**
 * Decimal handler.
 *
 * Мы не используем float из-за потери точности.
 * В PHP возвращаем нормализованную строку без экспоненты (`-12.50`) либо null.
 *
 * Правила:
 *  - принимаются int, конечный float и строки вида `12`, `-12.5`, `.5`, `+1.0`, `1.5E+3`;
 *  - float переводится в строку по кратчайшему точному представлению (`1e20` → `100000000000000000000`);
 *  - опция `scale` дополняет дробную часть нулями до scale, а лишние значащие знаки приводят
 *    к исключению (округление не выполняется, чтобы не терять точность молча);
 *  - опция `trim_trailing_zeros` убирает хвостовые нули дробной части (применяется после scale).
 */
final class DecimalHandler implements TypeHandlerInterface
{
    public function supports(string $type): bool
    {
        return $type === 'decimal';
    }

    public function castTo(mixed $value, array $options = []): ?string
    {
        return $this->castFrom($value, $options);
    }

    public function castFrom(mixed $value, array $options = []): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->applyOptions($this->normalize($value), $options);
    }

    public function cast(mixed $value): mixed
    {
        return $this->castFrom($value);
    }

    private function normalize(mixed $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                throw new InvalidArgumentException('Invalid decimal value: non-finite float.');
            }

            $value = $this->shortestFloatString($value);
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid decimal value.');
        }

        $raw = trim($value);
        if (preg_match('/^([+-]?)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/', $raw, $matches) !== 1) {
            throw new InvalidArgumentException('Invalid decimal value: ' . $value);
        }

        $sign     = $matches[1] === '-' ? '-' : '';
        $whole    = $matches[2];
        $fraction = $matches[3] ?? '';
        $exponent = (int) ($matches[4] ?? '0');

        if ($whole === '' && $fraction === '') {
            throw new InvalidArgumentException('Invalid decimal value: ' . $value);
        }

        // Раскрываем экспоненту сдвигом десятичной точки.
        if ($exponent > 0) {
            $fraction = str_pad($fraction, $exponent, '0', STR_PAD_RIGHT);
            $whole .= substr($fraction, 0, $exponent);
            $fraction = substr($fraction, $exponent);
        } elseif ($exponent < 0) {
            $shift    = -$exponent;
            $whole    = str_repeat('0', $shift) . $whole;
            $fraction = substr($whole, -$shift) . $fraction;
            $whole    = substr($whole, 0, -$shift);
        }

        $whole = ltrim($whole, '0');
        if ($whole === '') {
            $whole = '0';
        }

        $result = $fraction !== '' ? $whole . '.' . $fraction : $whole;
        if ($sign === '-' && rtrim($fraction, '0') === '' && $whole === '0') {
            // -0 и -0.00 нормализуем без знака.
            return $result;
        }

        return $sign . $result;
    }

    private function applyOptions(string $value, array $options): string
    {
        $scale     = $options['scale'] ?? null;
        $trimZeros = (bool) ($options['trim_trailing_zeros'] ?? false);

        [$whole, $fraction] = $this->split($value);

        if (is_int($scale) && $scale >= 0) {
            $significant = rtrim($fraction, '0');
            if (strlen($significant) > $scale) {
                throw new InvalidArgumentException('Too many decimal places for decimal value with scale ' . $scale . ': ' . $value);
            }

            $fraction = str_pad(substr($fraction, 0, $scale), $scale, '0', STR_PAD_RIGHT);
        }

        if ($trimZeros) {
            $fraction = rtrim($fraction, '0');
        }

        return $fraction !== '' ? $whole . '.' . $fraction : $whole;
    }

    /**
     * @return array{string, string}
     */
    private function split(string $value): array
    {
        $dot = strpos($value, '.');
        if ($dot === false) {
            return [$value, ''];
        }

        return [substr($value, 0, $dot), substr($value, $dot + 1)];
    }

    /**
     * Кратчайшее десятичное представление float, которое читается обратно в тот же float.
     *
     * Не зависит от ini `precision`/`serialize_precision`: (string) округляет до 14 знаков
     * (`0.1 + 0.2` → `0.3`), что скрывало бы неточность исходного значения. Экспонента раскрывается в normalize().
     */
    private function shortestFloatString(float $value): string
    {
        for ($precision = 1; $precision < 17; $precision++) {
            $candidate = sprintf('%.' . $precision . 'G', $value);
            if ((float) $candidate === $value) {
                return $this->trimMantissaZeros($candidate);
            }
        }

        return $this->trimMantissaZeros(sprintf('%.17G', $value));
    }

    /**
     * Убирает незначащие нули мантиссы (`1.0E-20` → `1E-20`), которые sprintf('%G') может оставить.
     */
    private function trimMantissaZeros(string $value): string
    {
        $exponentPos = strpos($value, 'E');
        $mantissa    = $exponentPos === false ? $value : substr($value, 0, $exponentPos);
        $exponent    = $exponentPos === false ? '' : substr($value, $exponentPos);

        if (strpos($mantissa, '.') !== false) {
            $mantissa = rtrim(rtrim($mantissa, '0'), '.');
        }

        return $mantissa . $exponent;
    }
}
