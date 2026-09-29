<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Handlers;

use BackedEnum;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use PhpSoftBox\DataCasting\Contracts\TypeHandlerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Stringable;
use Throwable;

use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_infinite;
use function is_int;
use function is_nan;
use function is_numeric;
use function is_string;
use function preg_match;
use function str_replace;
use function strcasecmp;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use function var_export;

use const INF;
use const NAN;

/**
 * PostgreSQL array.
 *
 * В БД приходит литерал массива: `{a,"b c",NULL,"NULL"}`, `{{1,2},{3,4}}`, `[0:1]={1,2}`.
 * В PHP возвращаем list<mixed> (для многомерных массивов — вложенные list).
 *
 * Разбор и сборка следуют правилам PostgreSQL:
 *  - строки в кавычках могут содержать любые символы, `"` и `\` экранируются обратным слешем;
 *  - `NULL` без кавычек (без учёта регистра) — это null, а `"NULL"` — строка;
 *  - у элементов без кавычек отбрасываются внешние пробелы;
 *  - при сборке в кавычки берутся пустые строки, `NULL`, строки с пробелами и спецсимволами.
 *
 * Опция item_type при чтении приводит листовые элементы к типу:
 * string, int, float, bool, uuid (UuidInterface), datetime (DateTimeImmutable).
 */
final class PgArrayHandler implements TypeHandlerInterface
{
    private const array ITEM_TYPES = ['string', 'int', 'float', 'bool', 'uuid', 'datetime'];

    public function supports(string $type): bool
    {
        return $type === 'pg_array';
    }

    public function castTo(mixed $value, array $options = []): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw new InvalidArgumentException('PgArray value must be array|null.');
        }

        return $this->encodeArray($value);
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('PgArray value must be string|array|null.');
        }

        if ($value === '') {
            return ($options['empty_string_as_empty_array'] ?? true) ? [] : null;
        }

        $itemType = $options['item_type'] ?? null;
        $itemType = is_string($itemType) && $itemType !== '' ? $itemType : null;
        if ($itemType !== null && !in_array($itemType, self::ITEM_TYPES, true)) {
            throw new InvalidArgumentException('Unsupported pg_array item_type: ' . $itemType);
        }

        $items = $this->parse($value);

        return $itemType === null ? $items : $this->castItems($items, $itemType);
    }

    public function cast(mixed $value): mixed
    {
        return $this->castFrom($value);
    }

    /**
     * @param array<array-key, mixed> $value
     */
    private function encodeArray(array $value): string
    {
        $parts = [];
        foreach ($value as $item) {
            $parts[] = is_array($item) ? $this->encodeArray($item) : $this->encodeItem($item);
        }

        return '{' . implode(',', $parts) . '}';
    }

    private function encodeItem(mixed $item): string
    {
        if ($item === null) {
            return 'NULL';
        }

        if (is_bool($item)) {
            return $item ? 't' : 'f';
        }

        if (is_int($item)) {
            return (string) $item;
        }

        if (is_float($item)) {
            return match (true) {
                is_nan($item)      => 'NaN',
                is_infinite($item) => $item > 0 ? 'Infinity' : '-Infinity',
                default            => var_export($item, true),
            };
        }

        $string = match (true) {
            is_string($item)                   => $item,
            $item instanceof BackedEnum        => (string) $item->value,
            $item instanceof DateTimeInterface => $item->format('Y-m-d H:i:s.uP'),
            $item instanceof Stringable        => (string) $item,
            default                            => throw new InvalidArgumentException('Unsupported pg_array item value.'),
        };

        return $this->needsQuoting($string)
            ? '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $string) . '"'
            : $string;
    }

    private function needsQuoting(string $value): bool
    {
        return $value === ''
            || strcasecmp($value, 'NULL') === 0
            || preg_match('/[{}",\\\\\s]/', $value) === 1;
    }

    /**
     * @return list<mixed>
     */
    private function parse(string $literal): array
    {
        $literal = trim($literal);

        // Необязательное указание границ измерений: `[1:3]={...}` или `[0:1][0:1]={...}`.
        if ($literal !== '' && $literal[0] === '[') {
            $eq = strpos($literal, '=');
            if ($eq === false) {
                throw new InvalidArgumentException('Invalid pg array literal.');
            }

            $literal = trim(substr($literal, $eq + 1));
        }

        if ($literal === '' || $literal[0] !== '{') {
            throw new InvalidArgumentException('Invalid pg array literal.');
        }

        $pos    = 0;
        $result = $this->parseArray($literal, $pos);

        if (trim(substr($literal, $pos)) !== '') {
            throw new InvalidArgumentException('Invalid pg array literal: unexpected trailing characters.');
        }

        return $result;
    }

    /**
     * Разбирает массив, начиная с `{` в позиции $pos; после выхода $pos указывает на символ после `}`.
     *
     * @return list<mixed>
     */
    private function parseArray(string $literal, int &$pos): array
    {
        $length = strlen($literal);
        $pos++; // пропускаем '{'
        $result = [];

        $this->skipWhitespace($literal, $pos);
        if ($pos < $length && $literal[$pos] === '}') {
            $pos++;

            return $result;
        }

        while (true) {
            $this->skipWhitespace($literal, $pos);
            if ($pos >= $length) {
                throw new InvalidArgumentException('Invalid pg array literal: unexpected end.');
            }

            $ch = $literal[$pos];
            if ($ch === '{') {
                $result[] = $this->parseArray($literal, $pos);
            } elseif ($ch === '"') {
                $result[] = $this->parseQuoted($literal, $pos);
            } else {
                $result[] = $this->parseUnquoted($literal, $pos);
            }

            $this->skipWhitespace($literal, $pos);
            if ($pos >= $length) {
                throw new InvalidArgumentException('Invalid pg array literal: unexpected end.');
            }

            if ($literal[$pos] === ',') {
                $pos++;
                continue;
            }

            if ($literal[$pos] === '}') {
                $pos++;

                return $result;
            }

            throw new InvalidArgumentException('Invalid pg array literal: unexpected character "' . $literal[$pos] . '".');
        }
    }

    private function parseQuoted(string $literal, int &$pos): string
    {
        $length = strlen($literal);
        $pos++; // пропускаем открывающую кавычку
        $buffer = '';

        while ($pos < $length) {
            $ch = $literal[$pos];

            if ($ch === '\\') {
                if ($pos + 1 >= $length) {
                    break;
                }

                $buffer .= $literal[$pos + 1];
                $pos += 2;
                continue;
            }

            if ($ch === '"') {
                $pos++;

                return $buffer;
            }

            $buffer .= $ch;
            $pos++;
        }

        throw new InvalidArgumentException('Invalid pg array literal: unterminated quoted element.');
    }

    private function parseUnquoted(string $literal, int &$pos): ?string
    {
        $length = strlen($literal);
        $buffer = '';
        // Длина буфера без хвостовых пробелов; экранированные символы тоже считаются значащими.
        $significant = 0;

        while ($pos < $length) {
            $ch = $literal[$pos];

            if ($ch === ',' || $ch === '}') {
                break;
            }

            if ($ch === '{' || $ch === '"') {
                throw new InvalidArgumentException('Invalid pg array literal: unexpected character "' . $ch . '".');
            }

            if ($ch === '\\') {
                if ($pos + 1 >= $length) {
                    throw new InvalidArgumentException('Invalid pg array literal: unexpected end.');
                }

                $buffer .= $literal[$pos + 1];
                $significant = strlen($buffer);
                $pos += 2;
                continue;
            }

            $buffer .= $ch;
            if (!$this->isWhitespace($ch)) {
                $significant = strlen($buffer);
            }

            $pos++;
        }

        $token = substr($buffer, 0, $significant);
        if ($token === '') {
            throw new InvalidArgumentException('Invalid pg array literal: empty unquoted element.');
        }

        // NULL без кавычек (и без экранирования) — это null.
        if (strcasecmp($token, 'NULL') === 0 && $significant === 4 && strpos($buffer, '\\') === false) {
            return null;
        }

        return $token;
    }

    private function skipWhitespace(string $literal, int &$pos): void
    {
        $length = strlen($literal);
        while ($pos < $length && $this->isWhitespace($literal[$pos])) {
            $pos++;
        }
    }

    private function isWhitespace(string $ch): bool
    {
        return $ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r" || $ch === "\v" || $ch === "\f";
    }

    /**
     * @param list<mixed> $items
     * @return list<mixed>
     */
    private function castItems(array $items, string $itemType): array
    {
        $result = [];
        foreach ($items as $item) {
            $result[] = is_array($item)
                ? $this->castItems($item, $itemType)
                : $this->castItem($item, $itemType);
        }

        return $result;
    }

    private function castItem(mixed $value, string $itemType): mixed
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return match ($itemType) {
            'int'      => $this->castInt($value),
            'float'    => $this->castFloat($value),
            'bool'     => $this->castBool($value),
            'uuid'     => $this->castUuid($value),
            'datetime' => $this->castDateTime($value),
            default    => $value,
        };
    }

    private function castInt(string $value): int
    {
        if (preg_match('/^[+-]?\d+$/', $value) !== 1) {
            throw new InvalidArgumentException('Invalid pg_array int item: ' . $value);
        }

        return new IntHandler()->castFrom($value) ?? 0;
    }

    private function castFloat(string $value): float
    {
        return match (strtolower($value)) {
            'nan'       => NAN,
            'infinity'  => INF,
            '-infinity' => -INF,
            default     => is_numeric($value)
                ? (float) $value
                : throw new InvalidArgumentException('Invalid pg_array float item: ' . $value),
        };
    }

    private function castBool(string $value): bool
    {
        return match (strtolower($value)) {
            't', 'true', '1', 'y', 'yes', 'on'  => true,
            'f', 'false', '0', 'n', 'no', 'off' => false,
            default                             => throw new InvalidArgumentException('Invalid pg_array bool item: ' . $value),
        };
    }

    private function castUuid(string $value): UuidInterface
    {
        try {
            return Uuid::fromString($value);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Invalid pg_array uuid item: ' . $value, 0, $e);
        }
    }

    private function castDateTime(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Invalid pg_array datetime item: ' . $value, 0, $e);
        }
    }
}
