<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests;

use JsonSerializable;
use PhpSoftBox\DataCasting\Attributes\JsonCollectionOf;
use PhpSoftBox\DataCasting\Attributes\JsonMapOf;
use PhpSoftBox\DataCasting\Contracts\JsonHydratableInterface;
use PhpSoftBox\DataCasting\Contracts\JsonValueFactoryInterface;
use PhpSoftBox\DataCasting\Contracts\JsonValueFactoryResolverInterface;
use PhpSoftBox\DataCasting\Exception\JsonHydrationException;
use PhpSoftBox\DataCasting\Handlers\JsonHandler;
use PhpSoftBox\DataCasting\JsonHydrationContext;
use PhpSoftBox\DataCasting\ReflectionJsonObjectMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonObjectMappingTest extends TestCase
{
    #[Test]
    public function plainJsonArrayRemainsBackwardCompatible(): void
    {
        $handler = new JsonHandler();

        self::assertSame(
            ['name' => 'shipment', 'nested' => ['enabled' => true]],
            $handler->castFrom('{"name":"shipment","nested":{"enabled":true}}'),
        );
        self::assertSame('{"name":"shipment"}', $handler->castTo(['name' => 'shipment']));
    }

    #[Test]
    public function recursivelyHydratesAndNormalizesObjectsCollectionsAndMaps(): void
    {
        $handler = new JsonHandler();
        $json    = <<<'JSON'
            {
                "source": "api",
                "address": {"city": "Moscow", "street": "Tverskaya"},
                "items": [
                    {"sku": "A-1", "quantity": 2},
                    {"sku": "B-2", "quantity": 1}
                ],
                "itemsBySku": {
                    "A-1": {"sku": "A-1", "quantity": 2}
                }
            }
            JSON;

        $metadata = $handler->castFrom($json, ['target_class' => ShipmentMetadata::class]);

        self::assertInstanceOf(ShipmentMetadata::class, $metadata);
        self::assertSame('api', $metadata->source);
        self::assertSame('Moscow', $metadata->address->city);
        self::assertNull($metadata->comment);
        self::assertContainsOnlyInstancesOf(ShipmentItemMetadata::class, $metadata->items);
        self::assertSame('B-2', $metadata->items[1]->sku);
        self::assertContainsOnlyInstancesOf(ShipmentItemMetadata::class, $metadata->itemsBySku);

        self::assertSame(
            '{"source":"api","address":{"city":"Moscow","street":"Tverskaya"},"comment":null,"items":[{"sku":"A-1","quantity":2},{"sku":"B-2","quantity":1}],"itemsBySku":{"A-1":{"sku":"A-1","quantity":2}}}',
            $handler->castTo($metadata, ['target_class' => ShipmentMetadata::class]),
        );
    }

    #[Test]
    public function emptyDtoMapIsEncodedAsJsonObject(): void
    {
        $handler  = new JsonHandler();
        $metadata = new ShipmentMetadata(
            source: 'api',
            address: new AddressMetadata('Moscow', 'Tverskaya'),
            comment: null,
            items: [],
            itemsBySku: [],
        );

        $json = $handler->castTo($metadata, ['target_class' => ShipmentMetadata::class]);

        self::assertStringContainsString('"items":[]', $json);
        self::assertStringContainsString('"itemsBySku":{}', $json);
    }

    #[Test]
    public function reportsFullPathForMissingRequiredNestedField(): void
    {
        $handler = new JsonHandler();

        $this->expectException(JsonHydrationException::class);
        $this->expectExceptionMessage('$.metadata.address.street is required.');

        $handler->castFrom(
            '{"source":"api","address":{"city":"Moscow"},"items":[],"itemsBySku":{}}',
            [
                'target_class' => ShipmentMetadata::class,
                'path'         => '$.metadata',
            ],
        );
    }

    #[Test]
    public function rejectsUnknownDtoFields(): void
    {
        $handler = new JsonHandler();

        $this->expectException(JsonHydrationException::class);
        $this->expectExceptionMessage('unknown JSON field "legacy"');

        $handler->castFrom(
            '{"source":"api","address":{"city":"Moscow","street":"Tverskaya"},"items":[],"itemsBySku":{},"legacy":true}',
            ['target_class' => ShipmentMetadata::class],
        );
    }

    #[Test]
    public function supportsTopLevelDtoCollectionsAndMaps(): void
    {
        $handler = new JsonHandler();

        $collection = $handler->castFrom(
            '[{"sku":"A-1","quantity":2}]',
            ['collection_item_class' => ShipmentItemMetadata::class],
        );
        $map = $handler->castFrom(
            '{"first":{"sku":"A-1","quantity":2}}',
            ['map_value_class' => ShipmentItemMetadata::class],
        );

        self::assertInstanceOf(ShipmentItemMetadata::class, $collection[0]);
        self::assertInstanceOf(ShipmentItemMetadata::class, $map['first']);
        self::assertSame(
            '{"first":{"sku":"A-1","quantity":2}}',
            $handler->castTo($map, ['map_value_class' => ShipmentItemMetadata::class]),
        );
    }

    #[Test]
    public function factoryCanSelectImplementationUsingSourceRow(): void
    {
        $handler = new JsonHandler();
        $context = new JsonHydrationContext(
            source: ['metadata_type' => 'fbs'],
            ownerClass: 'ShipmentEntity',
            property: 'metadata',
            path: '$.metadata',
        );

        $metadata = $handler->castFrom(
            '{"code":"FBS-1"}',
            [
                'target_class'      => PolymorphicMetadata::class,
                'factory_class'     => PolymorphicMetadataFactory::class,
                'hydration_context' => $context,
            ],
        );

        self::assertInstanceOf(FbsMetadata::class, $metadata);
        self::assertSame('FBS-1', $metadata->code);
    }

    #[Test]
    public function customResolverCanProvideFactoryWithDependencies(): void
    {
        $resolver = new class () implements JsonValueFactoryResolverInterface {
            public function resolve(string $factoryClass): JsonValueFactoryInterface
            {
                return new DependencyAwareMetadataFactory('resolved-');
            }
        };
        $handler = new JsonHandler(new ReflectionJsonObjectMapper($resolver));

        $metadata = $handler->castFrom(
            '{"code":"1"}',
            [
                'target_class'  => FbsMetadata::class,
                'factory_class' => DependencyAwareMetadataFactory::class,
            ],
        );

        self::assertInstanceOf(FbsMetadata::class, $metadata);
        self::assertSame('resolved-1', $metadata->code);
    }

    #[Test]
    public function nativeJsonSerializableAndCustomHydratorFormSymmetricContract(): void
    {
        $handler = new JsonHandler();

        $value = $handler->castFrom(
            '{"stored_name":"test"}',
            ['target_class' => CustomJsonValue::class],
        );

        self::assertInstanceOf(CustomJsonValue::class, $value);
        self::assertSame('test', $value->name);
        self::assertSame(
            '{"stored_name":"test"}',
            $handler->castTo($value, ['target_class' => CustomJsonValue::class]),
        );
    }
}

final readonly class ShipmentMetadata
{
    /**
     * @param list<ShipmentItemMetadata> $items
     * @param array<string, ShipmentItemMetadata> $itemsBySku
     */
    public function __construct(
        public string $source,
        public AddressMetadata $address,
        public ?string $comment,
        #[JsonCollectionOf(ShipmentItemMetadata::class)]
        public array $items,
        #[JsonMapOf(ShipmentItemMetadata::class)]
        public array $itemsBySku,
    ) {
    }
}

final readonly class AddressMetadata
{
    public function __construct(
        public string $city,
        public string $street,
    ) {
    }
}

final readonly class ShipmentItemMetadata
{
    public function __construct(
        public string $sku,
        public int $quantity,
    ) {
    }
}

interface PolymorphicMetadata
{
}

final readonly class FboMetadata implements PolymorphicMetadata
{
    public function __construct(
        public string $code,
    ) {
    }
}

final readonly class FbsMetadata implements PolymorphicMetadata
{
    public function __construct(
        public string $code,
    ) {
    }
}

final class PolymorphicMetadataFactory implements JsonValueFactoryInterface
{
    public function create(array $data, JsonHydrationContext $context): object
    {
        $class = $context->source['metadata_type'] === 'fbs' ? FbsMetadata::class : FboMetadata::class;

        return new $class($data['code']);
    }
}

final class DependencyAwareMetadataFactory implements JsonValueFactoryInterface
{
    public function __construct(
        private readonly string $prefix,
    ) {
    }

    public function create(array $data, JsonHydrationContext $context): object
    {
        return new FbsMetadata($this->prefix . $data['code']);
    }
}

final readonly class CustomJsonValue implements JsonHydratableInterface, JsonSerializable
{
    public function __construct(
        public string $name,
    ) {
    }

    public static function fromJsonData(array $data): static
    {
        return new self($data['stored_name']);
    }

    public function jsonSerialize(): array
    {
        return ['stored_name' => $this->name];
    }
}
