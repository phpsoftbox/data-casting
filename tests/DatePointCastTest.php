<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests;

use PhpSoftBox\Clock\DatePoint;
use PhpSoftBox\DataCasting\DefaultTypeCasterFactory;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\DataCasting\TypeCaster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypeCaster::class)]
#[CoversClass(TypeCastOptionsManager::class)]
#[CoversMethod(TypeCaster::class, 'castFrom')]
#[CoversMethod(TypeCastOptionsManager::class, 'resolve')]
final class DatePointCastTest extends TestCase
{
    /**
     * Проверим, что `date_point` с опциями по умолчанию (как их передаёт ORM) читает `TIMESTAMP` Postgres с
     * микросекундами и смещением.
     *
     * @see TypeCaster::castFrom()
     * @see TypeCastOptionsManager::resolve()
     */
    #[Test]
    public function readsPostgresTimestampWithFraction(): void
    {
        $caster = new DefaultTypeCasterFactory()->create();

        $options = new TypeCastOptionsManager()->resolve('date_point', null);

        $value = $caster->castFrom('date_point', '2026-04-22 12:30:45.123456+03', $options);

        self::assertInstanceOf(DatePoint::class, $value);
        self::assertSame('2026-04-22T12:30:45.123456+03:00', $value->format('Y-m-d\TH:i:s.uP'));
    }
}
