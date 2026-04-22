<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting;

use DateTimeImmutable;
use DateTimeInterface;
use PhpSoftBox\DataCasting\Contracts\JsonValueFactoryResolverInterface;
use PhpSoftBox\DataCasting\Handlers\BooleanHandler;
use PhpSoftBox\DataCasting\Handlers\DateTimeHandler;
use PhpSoftBox\DataCasting\Handlers\DecimalHandler;
use PhpSoftBox\DataCasting\Handlers\EnumHandler;
use PhpSoftBox\DataCasting\Handlers\FloatHandler;
use PhpSoftBox\DataCasting\Handlers\IntHandler;
use PhpSoftBox\DataCasting\Handlers\JsonHandler;
use PhpSoftBox\DataCasting\Handlers\MoneyHandler;
use PhpSoftBox\DataCasting\Handlers\PgArrayHandler;
use PhpSoftBox\DataCasting\Handlers\PhoneHandler;
use PhpSoftBox\DataCasting\Handlers\StringHandler;
use PhpSoftBox\DataCasting\Handlers\UuidHandler;

/**
 * Фабрика дефолтного TypeCaster.
 *
 * Её удобно настраивать через DI:
 *  - выбрать класс для DateTime (DateTimeImmutable/Carbon)
 *  - добавить/заменить handler'ы под свои типы
 */
final readonly class DefaultTypeCasterFactory
{
    /**
     * @param class-string<DateTimeInterface> $dateTimeClass
     */
    public function __construct(
        private string $dateTimeClass = DateTimeImmutable::class,
        private ?JsonValueFactoryResolverInterface $jsonValueFactoryResolver = null,
    ) {
    }

    public function create(): TypeCaster
    {
        return new TypeCaster([
            new IntHandler(),
            new FloatHandler(),
            new StringHandler(),
            new PhoneHandler(),
            new UuidHandler(),
            new JsonHandler(new ReflectionJsonObjectMapper(
                $this->jsonValueFactoryResolver ?? new JsonValueFactoryResolver(),
            )),
            new DateTimeHandler(dateTimeClass: $this->dateTimeClass),
            new BooleanHandler(),
            new DecimalHandler(),
            new MoneyHandler(),
            new PgArrayHandler(),
            new EnumHandler(),
        ]);
    }
}
