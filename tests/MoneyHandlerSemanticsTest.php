<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests;

use InvalidArgumentException;
use PhpSoftBox\DataCasting\Handlers\MoneyHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(MoneyHandler::class)]
#[CoversMethod(MoneyHandler::class, 'castTo')]
#[CoversMethod(MoneyHandler::class, 'castFrom')]
final class MoneyHandlerSemanticsTest extends TestCase
{
    /**
     * Проверим, что int в castTo трактуется как мажорные единицы так же, как строка.
     *
     * @see MoneyHandler::castTo()
     */
    #[Test]
    public function castToTreatsIntAndStringAsMajorUnits(): void
    {
        $handler = new MoneyHandler();

        self::assertSame(10000, $handler->castTo(100));
        self::assertSame(10000, $handler->castTo('100'));
    }

    /**
     * Проверим, что float, точно укладывающийся в scale, переводится в минорные единицы без потерь.
     *
     * @see MoneyHandler::castTo()
     */
    #[Test]
    public function castToAcceptsFloatRepresentableInScale(): void
    {
        $handler = new MoneyHandler();

        self::assertSame(1234, $handler->castTo(12.34));
    }

    /**
     * Проверим, что float с лишними знаками (результат арифметики) не округляется молча, а отклоняется.
     *
     * @see MoneyHandler::castTo()
     */
    #[Test]
    public function castToRejectsFloatWithExtraDecimals(): void
    {
        $handler = new MoneyHandler();

        $this->expectException(InvalidArgumentException::class);

        $handler->castTo(0.1 + 0.2);
    }

    /**
     * Проверим, что хвостовые нули за пределами scale допустимы.
     *
     * @see MoneyHandler::castTo()
     */
    #[Test]
    public function castToAcceptsTrailingZerosBeyondScale(): void
    {
        $handler = new MoneyHandler();

        self::assertSame(1234, $handler->castTo('12.340'));
    }

    /**
     * Проверим, что сумма вне диапазона PHP int отклоняется, а не переполняется.
     *
     * @see MoneyHandler::castTo()
     */
    #[Test]
    public function castToRejectsOverflow(): void
    {
        $handler = new MoneyHandler();

        $this->expectException(InvalidArgumentException::class);

        $handler->castTo('92233720368547758.08');
    }

    /**
     * Проверим, что castFrom трактует int и строку из цифр одинаково — как минорные единицы.
     *
     * @see MoneyHandler::castFrom()
     */
    #[Test]
    public function castFromTreatsIntAndStringAsMinorUnits(): void
    {
        $handler = new MoneyHandler();

        self::assertSame('100.00', $handler->castFrom(10000));
        self::assertSame('100.00', $handler->castFrom('10000'));
    }

    /**
     * Проверим, что дробный float из БД не усекается молча, а отклоняется.
     *
     * @see MoneyHandler::castFrom()
     */
    #[Test]
    public function castFromRejectsFractionalFloat(): void
    {
        $handler = new MoneyHandler();

        $this->expectException(InvalidArgumentException::class);

        $handler->castFrom(1050.5);
    }

    /**
     * Проверим, что castTo(castFrom(x)) возвращает исходные минорные единицы.
     *
     * @see MoneyHandler::castTo()
     * @see MoneyHandler::castFrom()
     */
    #[Test]
    public function roundTripKeepsMinorUnits(): void
    {
        $handler = new MoneyHandler();

        self::assertSame(-1050, $handler->castTo($handler->castFrom(-1050)));
    }
}
