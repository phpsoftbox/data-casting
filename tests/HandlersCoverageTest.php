<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests;

use DateTimeImmutable;
use InvalidArgumentException;
use PhpSoftBox\Clock\DatePoint;
use PhpSoftBox\DataCasting\Handlers\AbstractTypeHandler;
use PhpSoftBox\DataCasting\Handlers\BooleanHandler;
use PhpSoftBox\DataCasting\Handlers\DateTimeHandler;
use PhpSoftBox\DataCasting\Handlers\DecimalHandler;
use PhpSoftBox\DataCasting\Handlers\EncryptedHandler;
use PhpSoftBox\DataCasting\Handlers\EnumHandler;
use PhpSoftBox\DataCasting\Handlers\FloatHandler;
use PhpSoftBox\DataCasting\Handlers\IntHandler;
use PhpSoftBox\DataCasting\Handlers\JsonHandler;
use PhpSoftBox\DataCasting\Handlers\MoneyHandler;
use PhpSoftBox\DataCasting\Handlers\PgArrayHandler;
use PhpSoftBox\DataCasting\Handlers\PhoneHandler;
use PhpSoftBox\DataCasting\Handlers\StoragePathHandler;
use PhpSoftBox\DataCasting\Handlers\StringHandler;
use PhpSoftBox\DataCasting\Handlers\UuidHandler;
use PhpSoftBox\DataCasting\Tests\Fixtures\HandlerTestStatus;
use PhpSoftBox\Encryptor\Contracts\EncryptorInterface;
use PhpSoftBox\Storage\Storage;
use PhpSoftBox\Storage\StoragePath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\UuidInterface;

use function sys_get_temp_dir;

#[CoversClass(AbstractTypeHandler::class)]
#[CoversClass(BooleanHandler::class)]
#[CoversClass(DateTimeHandler::class)]
#[CoversClass(DecimalHandler::class)]
#[CoversClass(EncryptedHandler::class)]
#[CoversClass(EnumHandler::class)]
#[CoversClass(FloatHandler::class)]
#[CoversClass(IntHandler::class)]
#[CoversClass(JsonHandler::class)]
#[CoversClass(MoneyHandler::class)]
#[CoversClass(PgArrayHandler::class)]
#[CoversClass(PhoneHandler::class)]
#[CoversClass(StoragePathHandler::class)]
#[CoversClass(StringHandler::class)]
#[CoversClass(UuidHandler::class)]
final class HandlersCoverageTest extends TestCase
{
    /**
     * Проверяет базовое поведение AbstractTypeHandler.
     */
    #[Test]
    public function abstractHandlerProvidesDefaultCastingBehavior(): void
    {
        $handler = new class () extends AbstractTypeHandler {
            public function supports(string $type): bool
            {
                return $type === 'dummy';
            }
        };

        self::assertNull($handler->castTo(null));
        self::assertSame(123, $handler->castTo(123));
        self::assertSame('abc', $handler->castTo('abc'));
        $stringable = new class () {
            public function __toString(): string
            {
                return 'payload';
            }
        };
        self::assertSame('payload', $handler->castTo($stringable));
        self::assertSame(['a' => 1], $handler->castFrom(['a' => 1]));
    }

    /**
     * Проверяет кастинг bool-значений.
     */
    #[Test]
    public function booleanHandlerCastsValuesAndSupportsStrictMode(): void
    {
        $handler = new BooleanHandler();

        self::assertTrue($handler->supports('bool'));
        self::assertTrue($handler->supports('boolean'));
        self::assertTrue($handler->castFrom('yes'));
        self::assertFalse($handler->castFrom('no'));
        self::assertTrue($handler->castTo(1));
        self::assertNull($handler->castTo(null));

        $this->expectException(InvalidArgumentException::class);
        $handler->castFrom('not-bool', ['strict' => true]);
    }

    /**
     * Проверяет кастинг date/time и DatePoint.
     */
    #[Test]
    public function dateTimeHandlerCastsDateTimeAndDatePoint(): void
    {
        $handler = new DateTimeHandler(DateTimeImmutable::class);

        self::assertTrue($handler->supports('datetime'));
        self::assertTrue($handler->supports('date_point'));

        $parsed = $handler->castFrom('2026-04-22 12:00:00', ['format_from' => 'Y-m-d H:i:s']);
        self::assertInstanceOf(DateTimeImmutable::class, $parsed);
        self::assertSame('2026-04-22 12:00:00', $parsed->format('Y-m-d H:i:s'));

        $datePoint = $handler->castFrom('2026-04-22 12:00:00', [
            'type'        => 'date_point',
            'format_from' => 'Y-m-d H:i:s',
        ]);
        self::assertInstanceOf(DatePoint::class, $datePoint);
        self::assertSame('2026-04-22', $datePoint->format('Y-m-d'));

        $dbDate = $handler->castTo(new DateTimeImmutable('2026-04-22T12:30:45+00:00'), ['type' => 'date']);
        self::assertSame('2026-04-22', $dbDate);
    }

    /**
     * Проверяет decimal cast и trim trailing zeros.
     */
    #[Test]
    public function decimalHandlerCastsAndTrimsZeros(): void
    {
        $handler = new DecimalHandler();

        self::assertTrue($handler->supports('decimal'));
        self::assertSame('10.5', $handler->castFrom('10.5000', ['trim_trailing_zeros' => true]));
        self::assertSame('42', $handler->castTo(42));
        self::assertNull($handler->castFrom(''));

        $this->expectException(InvalidArgumentException::class);
        $handler->castTo(['bad']);
    }

    /**
     * Проверяет enum cast с null_on_invalid.
     */
    #[Test]
    public function enumHandlerCastsBackedEnums(): void
    {
        $handler = new EnumHandler();

        self::assertTrue($handler->supports('enum'));

        $enum = $handler->castFrom('active', ['enum_class' => HandlerTestStatus::class]);
        self::assertSame(HandlerTestStatus::Active, $enum);
        self::assertSame('inactive', $handler->castTo(HandlerTestStatus::Inactive));
        self::assertNull($handler->castFrom('unknown', [
            'enum_class'      => HandlerTestStatus::class,
            'null_on_invalid' => true,
        ]));

        $this->expectException(InvalidArgumentException::class);
        $handler->castFrom('active');
    }

    /**
     * Проверяет float cast.
     */
    #[Test]
    public function floatHandlerCastsNumericValues(): void
    {
        $handler = new FloatHandler();

        self::assertTrue($handler->supports('float'));
        self::assertTrue($handler->supports('double'));
        self::assertSame(12.5, $handler->castFrom('12.5'));
        self::assertNull($handler->castFrom(''));

        $this->expectException(InvalidArgumentException::class);
        $handler->castFrom('not-float');
    }

    /**
     * Проверяет int cast.
     */
    #[Test]
    public function intHandlerCastsNumericValues(): void
    {
        $handler = new IntHandler();

        self::assertTrue($handler->supports('int'));
        self::assertTrue($handler->supports('integer'));
        self::assertTrue($handler->supports('bigint'));
        self::assertTrue($handler->supports('bigInteger'));
        self::assertSame(42, $handler->castFrom('42'));
        self::assertNull($handler->castFrom(''));

        $this->expectException(InvalidArgumentException::class);
        $handler->castFrom('not-int');
    }

    /**
     * Проверяет, что int-caster отклоняет целые значения вне диапазона PHP int.
     */
    #[Test]
    public function intHandlerRejectsOutOfRangeIntegerStrings(): void
    {
        $handler = new IntHandler();

        $this->expectException(InvalidArgumentException::class);
        $handler->castFrom('9223372036854775808');
    }

    /**
     * Проверяет json cast и policy для невалидного json.
     */
    #[Test]
    public function jsonHandlerCastsAndHandlesInvalidPayload(): void
    {
        $handler = new JsonHandler();

        self::assertTrue($handler->supports('json'));
        self::assertSame('{"a":1}', $handler->castTo(['a' => 1]));
        self::assertSame(['a' => 1], $handler->castFrom('{"a":1}'));
        self::assertNull($handler->castFrom('bad json', ['invalid_json' => 'null']));

        $this->expectException(InvalidArgumentException::class);
        $handler->castFrom('bad json', ['invalid_json' => 'throw']);
    }

    /**
     * Проверяет money cast в обе стороны.
     */
    #[Test]
    public function moneyHandlerCastsMinorAndMajorValues(): void
    {
        $handler = new MoneyHandler();

        self::assertTrue($handler->supports('money'));
        self::assertSame('123.45', $handler->castFrom(12345));
        self::assertSame('12', $handler->castFrom(1200, ['trim_trailing_zeros' => true]));
        self::assertSame(12345, $handler->castTo('123.45'));
        self::assertSame(-99, $handler->castTo('-0.99'));

        $this->expectException(InvalidArgumentException::class);
        $handler->castTo('12.345');
    }

    /**
     * Проверяет pg_array cast и item_type преобразование.
     */
    #[Test]
    public function pgArrayHandlerCastsArrayValues(): void
    {
        $handler = new PgArrayHandler();

        self::assertTrue($handler->supports('pg_array'));

        $db = $handler->castTo(['a', 2, true, null]);
        self::assertSame('{a,2,t,NULL}', $db);

        $php = $handler->castFrom('{1,2,3}', ['item_type' => 'int']);
        self::assertSame([1, 2, 3], $php);
        self::assertSame([], $handler->castFrom(''));

        $this->expectException(InvalidArgumentException::class);
        $handler->castFrom('invalid');
    }

    /**
     * Проверяет преобразование phone-значений через PhoneFilter.
     */
    #[Test]
    public function phoneHandlerCastsWithPhoneFilter(): void
    {
        $handler = new PhoneHandler();

        self::assertTrue($handler->supports('phone'));
        self::assertNull($handler->castTo(null));

        $db  = $handler->castTo('+7 (999) 123-45-67');
        $php = $handler->castFrom('9991234567');

        self::assertSame('9991234567', $db);
        self::assertSame('(999) 123-4567', $php);
    }

    /**
     * Проверяет string cast для text-подобных типов.
     */
    #[Test]
    public function stringHandlerCastsTextLikeTypes(): void
    {
        $handler = new StringHandler();

        self::assertTrue($handler->supports('string'));
        self::assertTrue($handler->supports('text'));
        self::assertTrue($handler->supports('longtext'));
        self::assertSame('123', $handler->castFrom(123));
        self::assertSame('456', $handler->castTo(456));
        self::assertNull($handler->castFrom(null));
    }

    /**
     * Проверяет uuid cast в обе стороны.
     */
    #[Test]
    public function uuidHandlerCastsUuidValues(): void
    {
        $handler = new UuidHandler();

        self::assertTrue($handler->supports('uuid'));

        $uuid = $handler->castFrom('123e4567-e89b-12d3-a456-426655440000');
        self::assertInstanceOf(UuidInterface::class, $uuid);
        self::assertSame('123e4567-e89b-12d3-a456-426655440000', $handler->castTo($uuid));

        $this->expectException(InvalidArgumentException::class);
        $handler->castFrom(123);
    }

    /**
     * Проверяет encrypted cast в обе стороны.
     */
    #[Test]
    public function encryptedHandlerCastsValues(): void
    {
        $encryptor = $this->createMock(EncryptorInterface::class);
        $encryptor->expects($this->once())
            ->method('encrypt')
            ->with('secret', 'key')
            ->willReturn('encrypted');
        $encryptor->expects($this->once())
            ->method('decrypt')
            ->with('encrypted', 'key')
            ->willReturn('secret');

        $handler = new EncryptedHandler($encryptor);

        self::assertTrue($handler->supports('encrypted'));
        self::assertSame('encrypted', $handler->castTo('secret', ['key' => 'key']));
        self::assertSame('secret', $handler->castFrom('encrypted', ['key' => 'key']));
        self::assertNull($handler->castTo(null, ['key' => 'key']));
    }

    /**
     * Проверяет, что encrypted cast требует key в options.
     */
    #[Test]
    public function encryptedHandlerRejectsMissingKey(): void
    {
        $handler = new EncryptedHandler($this->createStub(EncryptorInterface::class));

        $this->expectException(InvalidArgumentException::class);
        $handler->castTo('secret');
    }

    /**
     * Проверяет storage_path cast с StoragePath object.
     */
    #[Test]
    public function storagePathHandlerCastsValues(): void
    {
        $handler = new StoragePathHandler();
        $storage = new Storage([
            'default' => 'public',
            'disks'   => [
                'public' => [
                    'driver'   => 'local',
                    'rootPath' => sys_get_temp_dir() . '/data-casting-storage-test',
                    'baseUrl'  => '/storage/public',
                ],
            ],
        ]);

        $path = $handler->castFrom('avatars/user-1.png', [
            'storage' => $storage,
            'disk'    => 'public',
        ]);

        self::assertTrue($handler->supports('storage_path'));
        self::assertInstanceOf(StoragePath::class, $path);
        self::assertSame('avatars/user-1.png', $path->path());
        self::assertSame('public', $path->disk());
        self::assertSame('/storage/public/avatars/user-1.png', $path->url());
        self::assertSame('avatars/user-1.png', $handler->castTo($path));
        self::assertNull($handler->castFrom(null));
    }

    /**
     * Проверяет, что storage_path cast требует Storage в options.
     */
    #[Test]
    public function storagePathHandlerRejectsMissingStorage(): void
    {
        $handler = new StoragePathHandler();

        $this->expectException(InvalidArgumentException::class);
        $handler->castFrom('avatars/user-1.png');
    }
}
