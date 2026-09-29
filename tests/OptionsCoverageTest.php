<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests;

use DateTimeImmutable;
use PhpSoftBox\Clock\DatePoint;
use PhpSoftBox\DataCasting\Options\BoolCastOptions;
use PhpSoftBox\DataCasting\Options\DatetimeCastOptions;
use PhpSoftBox\DataCasting\Options\DecimalCastOptions;
use PhpSoftBox\DataCasting\Options\EncryptedCastOptions;
use PhpSoftBox\DataCasting\Options\EnumCastOptions;
use PhpSoftBox\DataCasting\Options\JsonCastOptions;
use PhpSoftBox\DataCasting\Options\JsonInvalidPolicy;
use PhpSoftBox\DataCasting\Options\MoneyCastOptions;
use PhpSoftBox\DataCasting\Options\PgArrayCastOptions;
use PhpSoftBox\DataCasting\Options\PhoneCastOptions;
use PhpSoftBox\DataCasting\Options\StoragePathCastOptions;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;
use PhpSoftBox\DataCasting\Tests\Fixtures\OptionsCoverageStatus;
use PhpSoftBox\Storage\Storage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;

#[CoversClass(BoolCastOptions::class)]
#[CoversClass(DatetimeCastOptions::class)]
#[CoversClass(DecimalCastOptions::class)]
#[CoversClass(EncryptedCastOptions::class)]
#[CoversClass(EnumCastOptions::class)]
#[CoversClass(JsonCastOptions::class)]
#[CoversClass(JsonInvalidPolicy::class)]
#[CoversClass(MoneyCastOptions::class)]
#[CoversClass(PgArrayCastOptions::class)]
#[CoversClass(PhoneCastOptions::class)]
#[CoversClass(StoragePathCastOptions::class)]
#[CoversClass(TypeCastOptionsManager::class)]
final class OptionsCoverageTest extends TestCase
{
    /**
     * Проверяет дефолтные и кастомные значения bool options.
     */
    #[Test]
    public function boolOptionsExposeDefaultsAndCustomValues(): void
    {
        $defaults = new BoolCastOptions();
        $custom   = new BoolCastOptions(
            trueValues: [1, 'yes'],
            falseValues: [0, 'no'],
            strict: true,
        );

        self::assertSame([
            'true_values'  => null,
            'false_values' => null,
            'strict'       => null,
        ], $defaults->toArray());
        self::assertSame([
            'true_values'  => [1, 'yes'],
            'false_values' => [0, 'no'],
            'strict'       => true,
        ], $custom->toArray());
    }

    /**
     * Проверяет дефолтные и кастомные значения datetime options.
     */
    #[Test]
    public function datetimeOptionsExposeDefaultsAndCustomValues(): void
    {
        $defaults = new DatetimeCastOptions();
        $custom   = new DatetimeCastOptions('Y-m-d', '!Y-m-d', DatePoint::class);

        self::assertSame([
            'format_to'     => null,
            'format_from'   => null,
            'dateTimeClass' => null,
        ], $defaults->toArray());
        self::assertSame([
            'format_to'     => 'Y-m-d',
            'format_from'   => '!Y-m-d',
            'dateTimeClass' => DatePoint::class,
        ], $custom->toArray());
    }

    /**
     * Проверяет дефолтные и кастомные значения decimal/money options.
     */
    #[Test]
    public function decimalAndMoneyOptionsExposeDefaultsAndCustomValues(): void
    {
        $decimalDefaults = new DecimalCastOptions();
        $decimalCustom   = new DecimalCastOptions(scale: 4, trimTrailingZeros: true);
        $moneyDefaults   = new MoneyCastOptions();
        $moneyCustom     = new MoneyCastOptions(scale: 3, trimTrailingZeros: true);

        self::assertSame([
            'scale'               => null,
            'trim_trailing_zeros' => null,
        ], $decimalDefaults->toArray());
        self::assertSame([
            'scale'               => 4,
            'trim_trailing_zeros' => true,
        ], $decimalCustom->toArray());
        self::assertSame([
            'scale'               => null,
            'trim_trailing_zeros' => null,
        ], $moneyDefaults->toArray());
        self::assertSame([
            'scale'               => 3,
            'trim_trailing_zeros' => true,
        ], $moneyCustom->toArray());
    }

    /**
     * Проверяет дефолтные и кастомные значения enum options.
     */
    #[Test]
    public function enumOptionsExposeDefaultsAndCustomValues(): void
    {
        $defaults = new EnumCastOptions(OptionsCoverageStatus::class);
        $custom   = new EnumCastOptions(OptionsCoverageStatus::class, true);

        self::assertSame([
            'enum_class'      => OptionsCoverageStatus::class,
            'null_on_invalid' => null,
        ], $defaults->toArray());
        self::assertSame([
            'enum_class'      => OptionsCoverageStatus::class,
            'null_on_invalid' => true,
        ], $custom->toArray());
    }

    /**
     * Проверяет дефолтные и кастомные значения json options.
     */
    #[Test]
    public function jsonOptionsExposeDefaultsAndSupportAllInvalidPolicies(): void
    {
        $defaults = new JsonCastOptions();
        $custom   = new JsonCastOptions(1, 2, JsonInvalidPolicy::Throw);

        self::assertSame([
            'json_encode_flags'     => null,
            'json_decode_flags'     => null,
            'invalid_json'          => null,
            'target_class'          => null,
            'collection_item_class' => null,
            'map_value_class'       => null,
            'factory_class'         => null,
        ], $defaults->toArray());
        self::assertSame([
            'json_encode_flags'     => 1,
            'json_decode_flags'     => 2,
            'invalid_json'          => 'throw',
            'target_class'          => null,
            'collection_item_class' => null,
            'map_value_class'       => null,
            'factory_class'         => null,
        ], $custom->toArray());
        self::assertSame('empty', new JsonCastOptions(invalidJson: JsonInvalidPolicy::Empty)->toArray()['invalid_json']);
        self::assertSame('null', new JsonCastOptions(invalidJson: JsonInvalidPolicy::Null)->toArray()['invalid_json']);
        self::assertSame('throw', new JsonCastOptions(invalidJson: JsonInvalidPolicy::Throw)->toArray()['invalid_json']);
    }

    /**
     * Проверяет дефолтные и кастомные значения pg_array options.
     */
    #[Test]
    public function pgArrayOptionsExposeDefaultsAndCustomValues(): void
    {
        $defaults = new PgArrayCastOptions();
        $custom   = new PgArrayCastOptions(itemType: 'uuid', emptyStringAsEmptyArray: false);

        self::assertSame([
            'item_type'                   => null,
            'empty_string_as_empty_array' => null,
        ], $defaults->toArray());
        self::assertSame([
            'item_type'                   => 'uuid',
            'empty_string_as_empty_array' => false,
        ], $custom->toArray());
    }

    /**
     * Проверяет дефолтные и кастомные значения phone options.
     */
    #[Test]
    public function phoneOptionsExposeDefaultsAndCustomValues(): void
    {
        $defaults = new PhoneCastOptions();
        $custom   = new PhoneCastOptions(withCountryCodeTo: true, withCountryCodeFrom: true);

        self::assertSame([
            'with_country_code_to'   => null,
            'with_country_code_from' => null,
        ], $defaults->toArray());
        self::assertSame([
            'with_country_code_to'   => true,
            'with_country_code_from' => true,
        ], $custom->toArray());
    }

    /**
     * Проверяет toArray для encrypted options.
     */
    #[Test]
    public function encryptedOptionsExposeProvidedKey(): void
    {
        $encrypted = new EncryptedCastOptions('secret-key');

        self::assertSame(['key' => 'secret-key'], $encrypted->toArray());
    }

    /**
     * Проверяет toArray и фильтрацию null для storage_path options.
     */
    #[Test]
    public function storagePathOptionsFilterNullValues(): void
    {
        $storage = $this->createStorage();

        self::assertSame([], new StoragePathCastOptions()->toArray());
        self::assertSame(['disk' => 'public'], new StoragePathCastOptions('public')->toArray());
        self::assertSame(['storage' => $storage], new StoragePathCastOptions(storage: $storage)->toArray());
        self::assertSame([
            'disk'    => 'public',
            'storage' => $storage,
        ], new StoragePathCastOptions('public', $storage)->toArray());
    }

    /**
     * Проверяет дефолты менеджера опций для встроенных типов: заданы только форматы и класс DatePoint,
     * остальные типы не получают значений, чтобы не перекрывать дефолты handler'ов.
     *
     * @see TypeCastOptionsManager::resolve()
     */
    #[Test]
    public function managerResolvesBuiltInDefaults(): void
    {
        $manager = new TypeCastOptionsManager();

        self::assertSame([], $manager->resolve('datetime', null));
        self::assertSame(['format_to' => 'Y-m-d', 'format_from' => '!Y-m-d'], $manager->resolve('date', null));
        self::assertSame(['format_to' => 'H:i:s', 'format_from' => '!H:i:s'], $manager->resolve('time', null));
        self::assertSame(DatePoint::class, $manager->resolve('date_point', null)['dateTimeClass'] ?? null);
        self::assertSame('!Y-m-d', $manager->resolve('day_point', null)['format_from'] ?? null);
        self::assertSame('!H:i:s', $manager->resolve('time_point', null)['format_from'] ?? null);
        self::assertSame([], $manager->resolve('json', null));
        self::assertSame([], $manager->resolve('bool', null));
        self::assertSame([], $manager->resolve('decimal', null));
        self::assertSame([], $manager->resolve('pg_array', null));
        self::assertSame([], $manager->resolve('phone', null));
    }

    /**
     * Проверяет merge defaults + overrides, фильтрацию null и сохранение falsy значений.
     */
    #[Test]
    public function managerMergesDefaultsWithOverridesAndKeepsFalsyValues(): void
    {
        $manager = new TypeCastOptionsManager();

        $resolvedDatetime = $manager->resolve('datetime', new DatetimeCastOptions(
            formatTo: null,
            formatFrom: null,
            dateTimeClass: DateTimeImmutable::class,
        ));
        $resolvedBool = $manager->resolve('bool', new BoolCastOptions(
            trueValues: ['1'],
            falseValues: ['0', ''],
            strict: false,
        ));

        self::assertSame(DateTimeImmutable::class, $resolvedDatetime['dateTimeClass'] ?? null);
        self::assertArrayNotHasKey('format_to', $resolvedDatetime);
        self::assertArrayNotHasKey('format_from', $resolvedDatetime);
        self::assertSame(false, $resolvedBool['strict'] ?? null);
        self::assertSame(['1'], $resolvedBool['true_values'] ?? null);
        self::assertSame(['0', ''], $resolvedBool['false_values'] ?? null);
    }

    /**
     * Проверяет registerDefaults и повторную регистрацию дефолтов.
     */
    #[Test]
    public function managerSupportsCustomDefaultsRegistration(): void
    {
        $manager = new TypeCastOptionsManager();

        $manager->registerDefaults('encrypted', new EncryptedCastOptions('k1'));
        self::assertSame(['key' => 'k1'], $manager->resolve('encrypted', null));

        $manager->registerDefaults('encrypted', new EncryptedCastOptions('k2'));
        self::assertSame(['key' => 'k2'], $manager->resolve('encrypted', null));
    }

    /**
     * Проверяет resolve для неизвестного типа.
     */
    #[Test]
    public function managerHandlesUnknownTypeWithAndWithoutOverrides(): void
    {
        $manager = new TypeCastOptionsManager();

        self::assertSame([], $manager->resolve('unknown_type', null));
        self::assertSame([
            'scale'               => 5,
            'trim_trailing_zeros' => true,
        ], $manager->resolve('unknown_type', new DecimalCastOptions(5, true)));
    }

    private function createStorage(): Storage
    {
        return new Storage([
            'default' => 'public',
            'disks'   => [
                'public' => [
                    'driver'   => 'local',
                    'rootPath' => sys_get_temp_dir() . '/data-casting-storage-options-test',
                    'baseUrl'  => '/storage/public',
                ],
            ],
        ]);
    }
}
