<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests;

use PhpSoftBox\DataCasting\Tests\Fixtures\CountingTypeHandler;
use PhpSoftBox\DataCasting\TypeCaster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypeCaster::class)]
#[CoversMethod(TypeCaster::class, 'castFrom')]
#[CoversMethod(TypeCaster::class, 'castArray')]
final class TypeCasterHandlersTest extends TestCase
{
    /**
     * Проверим, что handler, переданный как class-string, создаётся один раз и переиспользуется.
     *
     * @see TypeCaster::castFrom()
     */
    #[Test]
    public function handlerClassStringIsInstantiatedOnce(): void
    {
        $caster                         = new TypeCaster([]);
        CountingTypeHandler::$instances = 0;

        $caster->castFrom(CountingTypeHandler::class, 'a');
        $caster->castFrom(CountingTypeHandler::class, 'b');

        self::assertSame(1, CountingTypeHandler::$instances);
    }

    /**
     * Проверим, что castArray передаёт в handler опции соответствующего поля.
     *
     * @see TypeCaster::castArray()
     */
    #[Test]
    public function castArrayPassesFieldOptions(): void
    {
        $caster = new TypeCaster([new CountingTypeHandler()]);

        $result = $caster->castArray(
            ['field' => 'counting'],
            ['field' => 'value', 'other' => 'untouched'],
            ['field' => ['flag' => true]],
        );

        self::assertSame([
            'field' => ['value' => 'value', 'options' => ['flag' => true, 'type' => 'counting']],
            'other' => 'untouched',
        ], $result);
    }
}
