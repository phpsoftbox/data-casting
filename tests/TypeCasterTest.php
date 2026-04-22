<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests;

use DateTimeImmutable;
use PhpSoftBox\DataCasting\DefaultTypeCasterFactory;
use PhpSoftBox\DataCasting\TypeCaster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;

#[CoversClass(TypeCaster::class)]
final class TypeCasterTest extends TestCase
{
    #[Test]
    public function factoryCreatesCasterWithDefaultHandlers(): void
    {
        $caster = new DefaultTypeCasterFactory()->create();

        self::assertSame(123, $caster->castFrom('int', '123'));
        self::assertSame('123', $caster->castTo('string', 123));
        self::assertSame(['a' => 1], $caster->castFrom('json', '{"a":1}'));
    }

    #[Test]
    public function casterSupportsUuidAndDateTime(): void
    {
        $caster = new DefaultTypeCasterFactory()->create();

        $uuid = $caster->castFrom('uuid', '123e4567-e89b-12d3-a456-426655440000');
        self::assertInstanceOf(UuidInterface::class, $uuid);

        $dateTime = $caster->castFrom('datetime', '2026-04-22T12:00:00+03:00');
        self::assertInstanceOf(DateTimeImmutable::class, $dateTime);
    }
}
