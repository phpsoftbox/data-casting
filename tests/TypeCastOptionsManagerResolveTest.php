<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests;

use PhpSoftBox\DataCasting\Options\DatetimeCastOptions;
use PhpSoftBox\DataCasting\Options\JsonCastOptions;
use PhpSoftBox\DataCasting\Options\JsonInvalidPolicy;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\DataCasting\Tests\Fixtures\CustomDateTime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TypeCastOptionsManager::class)]
#[CoversMethod(TypeCastOptionsManager::class, 'resolve')]
#[CoversMethod(TypeCastOptionsManager::class, 'registerDefaults')]
final class TypeCastOptionsManagerResolveTest extends TestCase
{
    /**
     * Проверим, что незаданные (null) поля объекта опций колонки не перекрывают зарегистрированные дефолты.
     *
     * @see TypeCastOptionsManager::registerDefaults()
     * @see TypeCastOptionsManager::resolve()
     */
    #[Test]
    public function nullOverrideFieldsKeepRegisteredDefaults(): void
    {
        $manager = new TypeCastOptionsManager();

        $manager->registerDefaults('datetime', new DatetimeCastOptions(dateTimeClass: CustomDateTime::class));

        $resolved = $manager->resolve('datetime', new DatetimeCastOptions(formatTo: 'Y-m-d H:i:s'));

        self::assertSame([
            'dateTimeClass' => CustomDateTime::class,
            'format_to'     => 'Y-m-d H:i:s',
        ], $resolved);
    }

    /**
     * Проверим, что заданное поле объекта опций колонки перекрывает зарегистрированный дефолт.
     *
     * @see TypeCastOptionsManager::resolve()
     */
    #[Test]
    public function explicitOverrideWinsOverRegisteredDefault(): void
    {
        $manager = new TypeCastOptionsManager();

        $manager->registerDefaults('json', new JsonCastOptions(invalidJson: JsonInvalidPolicy::Null));

        $resolved = $manager->resolve('json', new JsonCastOptions(invalidJson: JsonInvalidPolicy::Empty));

        self::assertSame(['invalid_json' => 'empty'], $resolved);
    }
}
