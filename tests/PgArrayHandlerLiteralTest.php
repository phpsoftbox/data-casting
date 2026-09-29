<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use PhpSoftBox\DataCasting\Handlers\PgArrayHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;

#[CoversClass(PgArrayHandler::class)]
#[CoversMethod(PgArrayHandler::class, 'castTo')]
#[CoversMethod(PgArrayHandler::class, 'castFrom')]
final class PgArrayHandlerLiteralTest extends TestCase
{
    /**
     * Проверим, что пустая строка и строка "NULL" берутся в кавычки и не путаются с NULL.
     *
     * @see PgArrayHandler::castTo()
     */
    #[Test]
    public function castToQuotesEmptyStringAndNullString(): void
    {
        $handler = new PgArrayHandler();

        self::assertSame('{"","NULL",NULL}', $handler->castTo(['', 'NULL', null]));
    }

    /**
     * Проверим, что кавычки, обратные слеши и пробелы экранируются.
     *
     * @see PgArrayHandler::castTo()
     */
    #[Test]
    public function castToEscapesSpecialCharacters(): void
    {
        $handler = new PgArrayHandler();

        self::assertSame('{"a \"b\"","c\\\\d"," e "}', $handler->castTo(['a "b"', 'c\\d', ' e ']));
    }

    /**
     * Проверим, что строки со спецсимволами переживают цикл castTo → castFrom без изменений.
     *
     * @see PgArrayHandler::castTo()
     * @see PgArrayHandler::castFrom()
     */
    #[Test]
    public function specialStringsSurviveRoundTrip(): void
    {
        $handler = new PgArrayHandler();
        $values  = ['', 'NULL', null, ' padded ', 'a,b', 'q"uote', 'back\\slash', '{braces}', "multi\nline"];

        self::assertSame($values, $handler->castFrom($handler->castTo($values)));
    }

    /**
     * Проверим, что NULL без кавычек — это null, а "NULL" в кавычках — строка.
     *
     * @see PgArrayHandler::castFrom()
     */
    #[Test]
    public function castFromDistinguishesNullAndQuotedNullString(): void
    {
        $handler = new PgArrayHandler();

        self::assertSame([null, 'NULL', null], $handler->castFrom('{NULL,"NULL",null}'));
    }

    /**
     * Проверим, что у элементов без кавычек отбрасываются внешние пробелы, а внутренние сохраняются.
     *
     * @see PgArrayHandler::castFrom()
     */
    #[Test]
    public function castFromTrimsOnlyOuterWhitespaceOfUnquotedItems(): void
    {
        $handler = new PgArrayHandler();

        self::assertSame(['a b', 'c'], $handler->castFrom('{ a b , c }'));
    }

    /**
     * Проверим разбор многомерного массива.
     *
     * @see PgArrayHandler::castFrom()
     */
    #[Test]
    public function castFromParsesMultiDimensionalArray(): void
    {
        $handler = new PgArrayHandler();

        self::assertSame([[1, 2], [3, null]], $handler->castFrom('{{1,2},{3,NULL}}', ['item_type' => 'int']));
    }

    /**
     * Проверим сборку многомерного массива.
     *
     * @see PgArrayHandler::castTo()
     */
    #[Test]
    public function castToEncodesMultiDimensionalArray(): void
    {
        $handler = new PgArrayHandler();

        self::assertSame('{{1,2},{"x y",NULL}}', $handler->castTo([[1, 2], ['x y', null]]));
    }

    /**
     * Проверим, что префикс с границами измерений пропускается.
     *
     * @see PgArrayHandler::castFrom()
     */
    #[Test]
    public function castFromSkipsDimensionDecoration(): void
    {
        $handler = new PgArrayHandler();

        self::assertSame(['1', '2'], $handler->castFrom('[0:1]={1,2}'));
    }

    /**
     * Проверим, что item_type uuid возвращает объекты UuidInterface.
     *
     * @see PgArrayHandler::castFrom()
     */
    #[Test]
    public function castFromCastsUuidItems(): void
    {
        $handler = new PgArrayHandler();

        $items = $handler->castFrom('{123e4567-e89b-12d3-a456-426655440000}', ['item_type' => 'uuid']);

        self::assertInstanceOf(UuidInterface::class, $items[0]);
        self::assertSame('123e4567-e89b-12d3-a456-426655440000', $items[0]->toString());
    }

    /**
     * Проверим, что item_type datetime возвращает объекты DateTimeImmutable.
     *
     * @see PgArrayHandler::castFrom()
     */
    #[Test]
    public function castFromCastsDateTimeItems(): void
    {
        $handler = new PgArrayHandler();

        $items = $handler->castFrom('{"2026-04-22 10:00:00+00"}', ['item_type' => 'datetime']);

        self::assertInstanceOf(DateTimeImmutable::class, $items[0]);
        self::assertSame('2026-04-22T10:00:00+00:00', $items[0]->format(DateTimeInterface::ATOM));
    }

    /**
     * Проверим, что нераспознанное значение для item_type int отклоняется, а не превращается в 0.
     *
     * @see PgArrayHandler::castFrom()
     */
    #[Test]
    public function castFromRejectsInvalidIntItem(): void
    {
        $handler = new PgArrayHandler();

        $this->expectException(InvalidArgumentException::class);

        $handler->castFrom('{1,abc}', ['item_type' => 'int']);
    }

    /**
     * Проверим, что неизвестный item_type отклоняется.
     *
     * @see PgArrayHandler::castFrom()
     */
    #[Test]
    public function castFromRejectsUnknownItemType(): void
    {
        $handler = new PgArrayHandler();

        $this->expectException(InvalidArgumentException::class);

        $handler->castFrom('{1}', ['item_type' => 'money']);
    }

    /**
     * Проверим, что незакрытая кавычка приводит к исключению.
     *
     * @see PgArrayHandler::castFrom()
     */
    #[Test]
    public function castFromRejectsUnterminatedQuote(): void
    {
        $handler = new PgArrayHandler();

        $this->expectException(InvalidArgumentException::class);

        $handler->castFrom('{"abc}');
    }
}
