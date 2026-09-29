<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests;

use InvalidArgumentException;
use PhpSoftBox\DataCasting\Handlers\JsonHandler;
use PhpSoftBox\DataCasting\Options\JsonInvalidPolicy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonHandler::class)]
#[CoversMethod(JsonHandler::class, 'castFrom')]
final class JsonHandlerInvalidPolicyTest extends TestCase
{
    /**
     * Проверим, что по умолчанию невалидный JSON приводит к исключению, а не к молчаливому `[]`.
     *
     * @see JsonHandler::castFrom()
     */
    #[Test]
    public function invalidJsonThrowsByDefault(): void
    {
        $handler = new JsonHandler();

        $this->expectException(InvalidArgumentException::class);

        $handler->castFrom('{broken');
    }

    /**
     * Проверим, что по умолчанию JSON-скаляр вместо объекта/массива приводит к исключению.
     *
     * @see JsonHandler::castFrom()
     */
    #[Test]
    public function scalarJsonThrowsByDefault(): void
    {
        $handler = new JsonHandler();

        $this->expectException(InvalidArgumentException::class);

        $handler->castFrom('"text"');
    }

    /**
     * Проверим, что JSON-литерал null читается как null.
     *
     * @see JsonHandler::castFrom()
     */
    #[Test]
    public function jsonNullLiteralBecomesNull(): void
    {
        $handler = new JsonHandler();

        self::assertNull($handler->castFrom('null'));
    }

    /**
     * Проверим, что прежнее поведение (пустой массив) доступно при явной политике Empty.
     *
     * @see JsonHandler::castFrom()
     */
    #[Test]
    public function emptyPolicyReturnsEmptyArrayWhenRequestedExplicitly(): void
    {
        $handler = new JsonHandler();

        self::assertSame([], $handler->castFrom('{broken', ['invalid_json' => JsonInvalidPolicy::Empty->value]));
    }
}
