<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests;

use PhpSoftBox\DataCasting\Exception\JsonHydrationException;
use PhpSoftBox\DataCasting\ReflectionJsonObjectMapper;
use PhpSoftBox\DataCasting\Tests\Fixtures\JsonConfigWithDefaults;
use PhpSoftBox\DataCasting\Tests\Fixtures\JsonConfigWithNullable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReflectionJsonObjectMapper::class)]
#[CoversMethod(ReflectionJsonObjectMapper::class, 'hydrateObject')]
final class ReflectionJsonObjectMapperDefaultsTest extends TestCase
{
    /**
     * Проверим, что отсутствующее в JSON поле с не-null дефолтом получает значение по умолчанию,
     * а не считается обязательным (старые строки читаются после добавления поля в VO).
     *
     * @see ReflectionJsonObjectMapper::hydrateObject()
     */
    #[Test]
    public function missingFieldWithNonNullDefaultUsesDefault(): void
    {
        $mapper = new ReflectionJsonObjectMapper();

        $config = $mapper->hydrateObject(JsonConfigWithDefaults::class, ['a' => 1]);

        self::assertInstanceOf(JsonConfigWithDefaults::class, $config);
        self::assertSame(1, $config->a);
        self::assertTrue($config->enabled);
    }

    /**
     * Проверим, что для nullable-поля с не-null дефолтом используется дефолт, а не null.
     *
     * @see ReflectionJsonObjectMapper::hydrateObject()
     */
    #[Test]
    public function missingNullableFieldWithDefaultUsesDefaultInsteadOfNull(): void
    {
        $mapper = new ReflectionJsonObjectMapper();

        $config = $mapper->hydrateObject(JsonConfigWithDefaults::class, ['a' => 1]);

        self::assertInstanceOf(JsonConfigWithDefaults::class, $config);
        self::assertSame('default comment', $config->comment);
        self::assertNull($config->note);
    }

    /**
     * Проверим, что отсутствующее nullable-поле без дефолта получает null.
     *
     * @see ReflectionJsonObjectMapper::hydrateObject()
     */
    #[Test]
    public function missingNullableFieldWithoutDefaultBecomesNull(): void
    {
        $mapper = new ReflectionJsonObjectMapper();

        $config = $mapper->hydrateObject(JsonConfigWithNullable::class, ['a' => 1]);

        self::assertInstanceOf(JsonConfigWithNullable::class, $config);
        self::assertNull($config->comment);
    }

    /**
     * Проверим, что отсутствующее не-nullable поле без дефолта по-прежнему считается обязательным.
     *
     * @see ReflectionJsonObjectMapper::hydrateObject()
     */
    #[Test]
    public function missingRequiredFieldThrows(): void
    {
        $mapper = new ReflectionJsonObjectMapper();

        $this->expectException(JsonHydrationException::class);
        $this->expectExceptionMessage('$.a is required.');

        $mapper->hydrateObject(JsonConfigWithDefaults::class, ['enabled' => false]);
    }
}
