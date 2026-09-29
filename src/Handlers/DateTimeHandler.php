<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Handlers;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use PhpSoftBox\Clock\DatePoint;
use PhpSoftBox\DataCasting\Contracts\TypeHandlerInterface;
use Throwable;

use function is_a;
use function is_string;

final readonly class DateTimeHandler implements TypeHandlerInterface
{
    /**
     * @param class-string<DateTimeInterface> $dateTimeClass
     */
    public function __construct(
        private string $dateTimeClass = DateTimeImmutable::class,
        private string $format = DateTimeInterface::ATOM,
    ) {
    }

    public function supports(string $type): bool
    {
        return $type === 'datetime'
            || $type === 'date'
            || $type === 'time'
            || $type === 'date_point'
            || $type === 'day_point'
            || $type === 'time_point';
    }

    public function castTo(mixed $value, array $options = []): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTimeInterface) {
            throw new InvalidArgumentException('Date/time value must implement DateTimeInterface.');
        }

        $type   = (string) ($options['type'] ?? 'datetime');
        $format = $options['format_to'] ?? match ($type) {
            'date', 'day_point'  => 'Y-m-d',
            'time', 'time_point' => 'H:i:s',
            'date_point'         => 'Y-m-d H:i:s',
            default              => $this->format,
        };

        return $value->format((string) $format);
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Invalid date/time value.');
        }

        $type       = (string) ($options['type'] ?? 'datetime');
        $class      = $options['dateTimeClass'] ?? $this->dateTimeClass;
        $formatFrom = $options['format_from'] ?? null;
        $formatFrom = is_string($formatFrom) && $formatFrom !== '' ? $formatFrom : null;

        if ($type === 'date_point' || $type === 'day_point' || $type === 'time_point') {
            $class = DatePoint::class;
        }

        if ($class === DatePoint::class) {
            return DatePoint::fromString($value, $formatFrom ?? $this->defaultFormatFrom($type));
        }

        // Если задан format_from, используем createFromFormat и не допускаем fallback.
        if ($formatFrom !== null) {
            $dt = $this->createFromFormat($class, $formatFrom, $value);
            if ($dt === null) {
                throw new InvalidArgumentException('Failed to parse date/time using format_from.');
            }

            return $dt;
        }

        // Для date/time без явного формата сначала пробуем строгий формат с `!`,
        // чтобы не подмешивать текущее время (для date) или текущую дату (для time).
        $defaultFormat = $this->defaultFormatFrom($type);
        if ($defaultFormat !== null) {
            $dt = $this->createFromFormat($class, $defaultFormat, $value);
            if ($dt !== null) {
                return $dt;
            }
        }

        try {
            return new $class($value);
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Failed to parse date/time.', 0, $e);
        }
    }

    public function cast(mixed $value): mixed
    {
        return $this->castFrom($value);
    }

    private function defaultFormatFrom(string $type): ?string
    {
        return match ($type) {
            'date', 'day_point'  => '!Y-m-d',
            'time', 'time_point' => '!H:i:s',
            default              => null,
        };
    }

    /**
     * @param class-string<DateTimeInterface> $class
     */
    private function createFromFormat(string $class, string $format, string $value): ?DateTimeInterface
    {
        // DateTimeImmutable/DateTime и их наследники (например, Carbon) создают объект нужного класса сами.
        if (is_a($class, DateTimeImmutable::class, true) || is_a($class, DateTime::class, true)) {
            $dt = $class::createFromFormat($format, $value);

            return $dt instanceof DateTimeInterface ? $dt : null;
        }

        $dt = DateTimeImmutable::createFromFormat($format, $value);
        if ($dt === false) {
            return null;
        }

        try {
            return new $class($dt->format('Y-m-d\\TH:i:s.uP'));
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Failed to convert date/time to configured dateTimeClass.', 0, $e);
        }
    }
}
