<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests;

use DateTimeImmutable;
use PhpSoftBox\DataCasting\DefaultTypeCasterFactory;
use PhpSoftBox\DataCasting\Handlers\DateTimeHandler;
use PhpSoftBox\DataCasting\Options\DatetimeCastOptions;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\DataCasting\Tests\Fixtures\CustomDateTime;
use PhpSoftBox\DataCasting\TypeCaster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DateTimeHandler::class)]
#[CoversClass(TypeCastOptionsManager::class)]
#[CoversMethod(DateTimeHandler::class, 'castFrom')]
#[CoversMethod(DateTimeHandler::class, 'castTo')]
#[CoversMethod(TypeCastOptionsManager::class, 'resolve')]
final class DateTimeHandlerTypesTest extends TestCase
{
    /**
     * Проверим, что тип date с дефолтными опциями менеджера не подмешивает текущее время.
     *
     * @see DateTimeHandler::castFrom()
     * @see TypeCastOptionsManager::resolve()
     */
    #[Test]
    public function dateWithManagerDefaultsHasMidnightTime(): void
    {
        $handler = new DateTimeHandler();
        $options = ['type' => 'date', ...new TypeCastOptionsManager()->resolve('date', null)];

        $date = $handler->castFrom('2026-04-22', $options);

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('2026-04-22 00:00:00.000000', $date->format('Y-m-d H:i:s.u'));
    }

    /**
     * Проверим, что тип time с дефолтными опциями менеджера не подмешивает текущую дату.
     *
     * @see DateTimeHandler::castFrom()
     * @see TypeCastOptionsManager::resolve()
     */
    #[Test]
    public function timeWithManagerDefaultsHasEpochDate(): void
    {
        $handler = new DateTimeHandler();
        $options = ['type' => 'time', ...new TypeCastOptionsManager()->resolve('time', null)];

        $time = $handler->castFrom('10:15:30', $options);

        self::assertInstanceOf(DateTimeImmutable::class, $time);
        self::assertSame('1970-01-01 10:15:30', $time->format('Y-m-d H:i:s'));
    }

    /**
     * Проверим, что date без format_from (прямой вызов handler'а) тоже разбирается без текущего времени.
     *
     * @see DateTimeHandler::castFrom()
     */
    #[Test]
    public function dateWithoutFormatFromHasMidnightTime(): void
    {
        $handler = new DateTimeHandler();

        $date = $handler->castFrom('2026-04-22', ['type' => 'date']);

        self::assertInstanceOf(DateTimeImmutable::class, $date);
        self::assertSame('00:00:00', $date->format('H:i:s'));
    }

    /**
     * Проверим, что TypeCaster передаёт тип в handler: castTo('date', ...) без опций даёт Y-m-d, а не ATOM.
     *
     * @see TypeCaster::castTo()
     * @see DateTimeHandler::castTo()
     */
    #[Test]
    public function casterPassesResolvedTypeToHandler(): void
    {
        $caster = new DefaultTypeCasterFactory()->create();

        $value = $caster->castTo('date', new DateTimeImmutable('2026-04-22 12:30:45'));

        self::assertSame('2026-04-22', $value);
    }

    /**
     * Проверим, что класс DateTime из DefaultTypeCasterFactory применяется, если опции колонки его не задают.
     *
     * @see DefaultTypeCasterFactory::create()
     * @see TypeCastOptionsManager::resolve()
     */
    #[Test]
    public function factoryDateTimeClassIsNotOverriddenByOptionsWithoutClass(): void
    {
        $caster  = new DefaultTypeCasterFactory(dateTimeClass: CustomDateTime::class)->create();
        $options = new TypeCastOptionsManager()->resolve('datetime', new DatetimeCastOptions(formatTo: 'Y-m-d H:i:s'));

        $value = $caster->castFrom('datetime', '2026-04-22T12:00:00+03:00', $options);

        self::assertInstanceOf(CustomDateTime::class, $value);
    }

    /**
     * Проверим, что format_from создаёт объект настроенного класса без потери микросекунд.
     *
     * @see DateTimeHandler::castFrom()
     */
    #[Test]
    public function formatFromCreatesConfiguredClass(): void
    {
        $handler = new DateTimeHandler(dateTimeClass: CustomDateTime::class);

        $value = $handler->castFrom('2026-04-22 12:00:00.123456', ['format_from' => 'Y-m-d H:i:s.u']);

        self::assertInstanceOf(CustomDateTime::class, $value);
        self::assertSame('123456', $value->format('u'));
    }
}
