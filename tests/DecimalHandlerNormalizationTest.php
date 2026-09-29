<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests;

use InvalidArgumentException;
use PhpSoftBox\DataCasting\Handlers\DecimalHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DecimalHandler::class)]
#[CoversMethod(DecimalHandler::class, 'castTo')]
#[CoversMethod(DecimalHandler::class, 'castFrom')]
final class DecimalHandlerNormalizationTest extends TestCase
{
    /**
     * Проверим, что большой float записывается без экспоненты.
     *
     * @see DecimalHandler::castTo()
     */
    #[Test]
    public function castToExpandsFloatExponent(): void
    {
        $handler = new DecimalHandler();

        self::assertSame('100000000000000000000', $handler->castTo(1e20));
    }

    /**
     * Проверим, что маленький float записывается без экспоненты.
     *
     * @see DecimalHandler::castTo()
     */
    #[Test]
    public function castToExpandsNegativeFloatExponent(): void
    {
        $handler = new DecimalHandler();

        self::assertSame('0.00000015', $handler->castTo(1.5e-7));
    }

    /**
     * Проверим, что scale дополняет дробную часть нулями.
     *
     * @see DecimalHandler::castFrom()
     */
    #[Test]
    public function scalePadsFraction(): void
    {
        $handler = new DecimalHandler();

        self::assertSame('10.50', $handler->castFrom('10.5', ['scale' => 2]));
    }

    /**
     * Проверим, что значение с лишними значащими знаками при заданном scale отклоняется.
     *
     * @see DecimalHandler::castTo()
     */
    #[Test]
    public function scaleRejectsExtraSignificantDigits(): void
    {
        $handler = new DecimalHandler();

        $this->expectException(InvalidArgumentException::class);

        $handler->castTo('10.555', ['scale' => 2]);
    }

    /**
     * Проверим, что нечисловая строка отклоняется.
     *
     * @see DecimalHandler::castTo()
     */
    #[Test]
    public function castToRejectsNonNumericString(): void
    {
        $handler = new DecimalHandler();

        $this->expectException(InvalidArgumentException::class);

        $handler->castTo('12abc');
    }

    /**
     * Проверим нормализацию знака, ведущих нулей и дробной части без целой.
     *
     * @see DecimalHandler::castFrom()
     */
    #[Test]
    public function castFromNormalizesNumericString(): void
    {
        $handler = new DecimalHandler();

        self::assertSame('0.5', $handler->castFrom('+.5'));
        self::assertSame('-12.30', $handler->castFrom('-0012.30'));
    }
}
