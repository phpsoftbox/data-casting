<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests;

use PhpSoftBox\DataCasting\Handlers\BooleanHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BooleanHandler::class)]
#[CoversMethod(BooleanHandler::class, 'castTo')]
final class BooleanHandlerCastToTest extends TestCase
{
    /**
     * Проверим, что строковые false-представления при записи дают false, а не (bool) 'false' === true.
     *
     * @see BooleanHandler::castTo()
     */
    #[Test]
    public function castToRecognizesFalseStrings(): void
    {
        $handler = new BooleanHandler();

        self::assertFalse($handler->castTo('false'));
        self::assertFalse($handler->castTo('OFF'));
        self::assertFalse($handler->castTo('f'));
    }
}
