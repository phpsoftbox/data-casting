<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Handlers;

use InvalidArgumentException;
use JsonException;
use PhpSoftBox\DataCasting\Contracts\JsonObjectMapperInterface;
use PhpSoftBox\DataCasting\Contracts\TypeHandlerInterface;
use PhpSoftBox\DataCasting\JsonHydrationContext;
use PhpSoftBox\DataCasting\ReflectionJsonObjectMapper;
use stdClass;

use function get_object_vars;
use function is_array;
use function is_object;
use function is_string;
use function json_decode;
use function json_encode;
use function json_last_error;

use const JSON_ERROR_NONE;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class JsonHandler implements TypeHandlerInterface
{
    private readonly JsonObjectMapperInterface $objectMapper;

    public function __construct(?JsonObjectMapperInterface $objectMapper = null)
    {
        $this->objectMapper = $objectMapper ?? new ReflectionJsonObjectMapper();
    }

    public function supports(string $type): bool
    {
        return $type === 'json';
    }

    public function castTo(mixed $value, array $options = []): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        $targetClass  = $this->stringOption($options, 'target_class');
        $collectionOf = $this->stringOption($options, 'collection_item_class');
        $mapOf        = $this->stringOption($options, 'map_value_class');
        $path         = $this->path($options);

        $this->assertSingleMapping($targetClass, $collectionOf, $mapOf);

        if ($collectionOf !== null) {
            if (!is_array($value)) {
                throw new InvalidArgumentException($path . ': JSON collection value must be an array.');
            }

            $value = $this->objectMapper->normalizeCollection($value, $collectionOf, $path);
        } elseif ($mapOf !== null) {
            if (!is_array($value)) {
                throw new InvalidArgumentException($path . ': JSON map value must be an array.');
            }

            $value = $this->objectMapper->normalizeMap($value, $mapOf, $path);
        } elseif ($targetClass !== null) {
            if (!is_object($value)) {
                throw new InvalidArgumentException($path . ': JSON object value must be an object.');
            }

            $value = $this->objectMapper->normalizeObject($value, $targetClass, $path);
        } elseif (!is_array($value)) {
            throw new InvalidArgumentException('JSON value must be array|string|null.');
        }

        $flags = (int) ($options['json_encode_flags'] ?? (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        try {
            $json = json_encode($value, $flags);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Failed to encode JSON.', previous: $exception);
        }
        if ($json === false) {
            throw new InvalidArgumentException('Failed to encode JSON.');
        }

        return $json;
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) && !is_array($value) && !$value instanceof stdClass) {
            throw new InvalidArgumentException('Invalid JSON value.');
        }

        $decoded = $value;
        if (is_string($value)) {
            $flags = (int) ($options['json_decode_flags'] ?? 0);
            try {
                $decoded = json_decode($value, false, flags: $flags);
            } catch (JsonException) {
                return $this->invalidJson($options);
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->invalidJson($options);
            }
        }

        if (!is_array($decoded) && !$decoded instanceof stdClass) {
            return $this->invalidJson($options);
        }

        $targetClass  = $this->stringOption($options, 'target_class');
        $collectionOf = $this->stringOption($options, 'collection_item_class');
        $mapOf        = $this->stringOption($options, 'map_value_class');
        $factoryClass = $this->stringOption($options, 'factory_class');
        $context      = $options['hydration_context'] ?? null;
        $context      = $context instanceof JsonHydrationContext ? $context : new JsonHydrationContext(path: $this->path($options));

        $this->assertSingleMapping($targetClass, $collectionOf, $mapOf);
        if ($factoryClass !== null && $targetClass === null && $collectionOf === null && $mapOf === null) {
            throw new InvalidArgumentException('JSON factory requires an object, collection or map target.');
        }

        if ($collectionOf !== null) {
            return $this->objectMapper->hydrateCollection($collectionOf, $decoded, $context, $factoryClass);
        }

        if ($mapOf !== null) {
            return $this->objectMapper->hydrateMap($mapOf, $decoded, $context, $factoryClass);
        }

        if ($targetClass !== null) {
            return $this->objectMapper->hydrateObject($targetClass, $decoded, $context, $factoryClass);
        }

        return $this->toArray($decoded);
    }

    public function cast(mixed $value): mixed
    {
        return $this->castFrom($value);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function invalidJson(array $options): mixed
    {
        $policy = (string) ($options['invalid_json'] ?? 'empty');
        $mapped = $this->stringOption($options, 'target_class') !== null
            || $this->stringOption($options, 'collection_item_class') !== null
            || $this->stringOption($options, 'map_value_class') !== null;

        return match ($policy) {
            'null'  => null,
            'throw' => throw new InvalidArgumentException('Invalid JSON string.'),
            default => $mapped
                ? throw new InvalidArgumentException('Invalid JSON string for mapped JSON value.')
                : [],
        };
    }

    private function stringOption(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function path(array $options): string
    {
        $path = $options['path'] ?? '$';

        return is_string($path) && $path !== '' ? $path : '$';
    }

    private function assertSingleMapping(?string $targetClass, ?string $collectionOf, ?string $mapOf): void
    {
        $configured = (int) ($targetClass !== null) + (int) ($collectionOf !== null) + (int) ($mapOf !== null);
        if ($configured > 1) {
            throw new InvalidArgumentException('JSON target, collection and map mappings are mutually exclusive.');
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function toArray(array|stdClass $value): array
    {
        $values = $value instanceof stdClass ? get_object_vars($value) : $value;
        $result = [];
        foreach ($values as $key => $item) {
            $result[$key] = match (true) {
                $item instanceof stdClass => $this->toArray($item),
                is_array($item)           => $this->toArray($item),
                default                   => $item,
            };
        }

        return $result;
    }
}
