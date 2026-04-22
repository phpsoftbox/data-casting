<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Options;

final readonly class JsonCastOptions implements TypeCastingOptionsInterface
{
    /**
     * @param class-string|null $targetClass
     * @param class-string|null $collectionItemClass
     * @param class-string|null $mapValueClass
     * @param class-string|null $factoryClass
     */
    public function __construct(
        public ?int $jsonEncodeFlags = null,
        public ?int $jsonDecodeFlags = null,
        public JsonInvalidPolicy $invalidJson = JsonInvalidPolicy::Empty,
        public ?string $targetClass = null,
        public ?string $collectionItemClass = null,
        public ?string $mapValueClass = null,
        public ?string $factoryClass = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'json_encode_flags'     => $this->jsonEncodeFlags,
            'json_decode_flags'     => $this->jsonDecodeFlags,
            'invalid_json'          => $this->invalidJson->value,
            'target_class'          => $this->targetClass,
            'collection_item_class' => $this->collectionItemClass,
            'map_value_class'       => $this->mapValueClass,
            'factory_class'         => $this->factoryClass,
        ];
    }
}
