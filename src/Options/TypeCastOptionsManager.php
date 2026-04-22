<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Options;

use PhpSoftBox\Clock\DatePoint;

use function array_filter;

/**
 * Менеджер опций: позволяет задавать дефолты и резолвить опции по type.
 *
 * Идея: типизированные опции задаются в #[Column(options: ...)] как объект.
 * При резолве мы:
 *  - берём дефолтные опции для типа
 *  - поверх применяем опции из атрибута
 *  - получаем итоговый массив options для handler'а
 */
final class TypeCastOptionsManager
{
    /**
     * @var array<string, TypeCastingOptionsInterface>
     */
    private array $defaults = [];

    public function __construct()
    {
        // Базовые дефолты (можно переопределять через registerDefaults()).
        $this->defaults['datetime'] = new DatetimeCastOptions();

        $this->defaults['date'] = new DatetimeCastOptions(formatTo: 'Y-m-d', formatFrom: 'Y-m-d');

        $this->defaults['time'] = new DatetimeCastOptions(formatTo: 'H:i:s', formatFrom: 'H:i:s');

        $this->defaults['date_point'] = new DatetimeCastOptions(
            formatTo: 'Y-m-d H:i:s',
            formatFrom: 'Y-m-d H:i:s',
            dateTimeClass: DatePoint::class,
        );

        $this->defaults['day_point'] = new DatetimeCastOptions(
            formatTo: 'Y-m-d',
            formatFrom: '!Y-m-d',
            dateTimeClass: DatePoint::class,
        );

        $this->defaults['time_point'] = new DatetimeCastOptions(
            formatTo: 'H:i:s',
            formatFrom: '!H:i:s',
            dateTimeClass: DatePoint::class,
        );

        $this->defaults['json'] = new JsonCastOptions();

        $this->defaults['bool'] = new BoolCastOptions();

        $this->defaults['boolean'] = new BoolCastOptions();

        $this->defaults['decimal'] = new DecimalCastOptions();

        $this->defaults['pg_array'] = new PgArrayCastOptions();

        $this->defaults['phone'] = new PhoneCastOptions();

        // enum/encrypted имеют обязательные параметры (enum_class/key), поэтому их обычно задают в #[Column].
        // Но дефолтные опции всё равно можно зарегистрировать через DI.
    }

    public function registerDefaults(string $type, TypeCastingOptionsInterface $defaults): void
    {
        $this->defaults[$type] = $defaults;
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(string $type, ?TypeCastingOptionsInterface $overrides): array
    {
        $base = $this->defaults[$type] ?? null;

        $baseArray      = $base?->toArray() ?? [];
        $overridesArray = $overrides?->toArray() ?? [];

        // overrides выигрывают
        return array_filter(
            [...$baseArray, ...$overridesArray],
            static fn (mixed $v): bool => $v !== null,
        );
    }
}
