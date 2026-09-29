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
        // Здесь задаются только значения, отличающиеся от дефолтов handler'ов: всё незаданное
        // (null) handler берёт из собственных настроек, например класс DateTime из DefaultTypeCasterFactory.
        // Форматы разбора с `!` обнуляют незаданные части, чтобы date/time не подмешивали текущее время/дату.
        $this->defaults['date'] = new DatetimeCastOptions(formatTo: 'Y-m-d', formatFrom: '!Y-m-d');

        $this->defaults['time'] = new DatetimeCastOptions(formatTo: 'H:i:s', formatFrom: '!H:i:s');

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

        // null означает «не задано»: такое поле не перекрывает ни дефолт типа, ни дефолт handler'а.
        $notNull        = static fn (mixed $v): bool => $v !== null;
        $baseArray      = array_filter($base?->toArray() ?? [], $notNull);
        $overridesArray = array_filter($overrides?->toArray() ?? [], $notNull);

        // Заданные overrides выигрывают.
        return [...$baseArray, ...$overridesArray];
    }
}
