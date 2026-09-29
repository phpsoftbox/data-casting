<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Options;

/**
 * Опции json-кастинга.
 *
 * Незаданные (null) поля не попадают в итоговые опции: вместо них используются дефолты,
 * зарегистрированные в TypeCastOptionsManager, а затем дефолты JsonHandler.
 */
final readonly class JsonCastOptions implements TypeCastingOptionsInterface
{
    /**
     * @param JsonInvalidPolicy|null $invalidJson Реакция на невалидный или скалярный JSON
     *                                            (по умолчанию JsonInvalidPolicy::Throw).
     * @param class-string|null $targetClass
     * @param class-string|null $collectionItemClass
     * @param class-string|null $mapValueClass
     * @param class-string|null $factoryClass
     */
    public function __construct(
        public ?int $jsonEncodeFlags = null,
        public ?int $jsonDecodeFlags = null,
        public ?JsonInvalidPolicy $invalidJson = null,
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
            'invalid_json'          => $this->invalidJson?->value,
            'target_class'          => $this->targetClass,
            'collection_item_class' => $this->collectionItemClass,
            'map_value_class'       => $this->mapValueClass,
            'factory_class'         => $this->factoryClass,
        ];
    }
}
