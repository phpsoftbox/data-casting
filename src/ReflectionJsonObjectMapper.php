<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting;

use BackedEnum;
use JsonSerializable;
use PhpSoftBox\DataCasting\Attributes\JsonCollectionOf;
use PhpSoftBox\DataCasting\Attributes\JsonFactory;
use PhpSoftBox\DataCasting\Attributes\JsonMapOf;
use PhpSoftBox\DataCasting\Contracts\JsonHydratableInterface;
use PhpSoftBox\DataCasting\Contracts\JsonObjectMapperInterface;
use PhpSoftBox\DataCasting\Contracts\JsonValueFactoryResolverInterface;
use PhpSoftBox\DataCasting\Exception\JsonHydrationException;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;
use stdClass;
use Throwable;
use UnitEnum;

use function array_diff_key;
use function array_fill_keys;
use function array_is_list;
use function array_key_exists;
use function array_keys;
use function array_map;
use function get_object_vars;
use function is_a;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_scalar;
use function is_string;
use function spl_object_id;
use function sprintf;

final class ReflectionJsonObjectMapper implements JsonObjectMapperInterface
{
    /**
     * @var array<int, true>
     */
    private array $normalizing = [];

    public function __construct(
        private readonly JsonValueFactoryResolverInterface $factoryResolver = new JsonValueFactoryResolver(),
    ) {
    }

    public function hydrateObject(
        string $class,
        mixed $data,
        ?JsonHydrationContext $context = null,
        ?string $factoryClass = null,
    ): object {
        $context ??= new JsonHydrationContext();
        $values = $this->objectValues($data, $context->path);

        if ($factoryClass !== null) {
            $result = $this->factoryResolver->resolve($factoryClass)->create(
                $this->toPhpArray($values),
                $context,
            );

            if (!$result instanceof $class) {
                throw new JsonHydrationException(sprintf(
                    '%s: JSON factory %s returned %s, expected %s.',
                    $context->path,
                    $factoryClass,
                    $result::class,
                    $class,
                ));
            }

            return $result;
        }

        if (is_a($class, JsonHydratableInterface::class, true)) {
            $result = $class::fromJsonData($this->toPhpArray($values));
            if (!$result instanceof $class) {
                throw new JsonHydrationException($context->path . ': JSON hydrator returned an unexpected object.');
            }

            return $result;
        }

        try {
            $reflection = new ReflectionClass($class);
        } catch (Throwable $exception) {
            throw new JsonHydrationException($context->path . ': unable to reflect JSON target ' . $class . '.', previous: $exception);
        }

        if (!$reflection->isInstantiable()) {
            throw new JsonHydrationException(
                $context->path . ': JSON target ' . $class . ' is not instantiable; configure #[JsonFactory].',
            );
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            if ($values !== []) {
                throw new JsonHydrationException(
                    $context->path . ': JSON target without a constructor cannot accept object fields.',
                );
            }

            return $reflection->newInstance();
        }

        $parameters = $constructor->getParameters();
        $known      = array_fill_keys(array_map(static fn (ReflectionParameter $parameter): string => $parameter->getName(), $parameters), true);
        $unknown    = array_diff_key($values, $known);
        if ($unknown !== []) {
            throw new JsonHydrationException(sprintf(
                '%s: unknown JSON field "%s" for %s.',
                $context->path,
                (string) array_keys($unknown)[0],
                $class,
            ));
        }

        $arguments = [];
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $path = $context->path . '.' . $name;

            if (!array_key_exists($name, $values)) {
                // Отсутствующее поле: сначала используем значение по умолчанию из конструктора
                // (новое поле VO не должно ломать чтение старых данных), затем null для nullable-параметра.
                if ($parameter->isDefaultValueAvailable()) {
                    continue;
                }

                if (!$parameter->allowsNull()) {
                    throw new JsonHydrationException($path . ' is required.');
                }

                $arguments[$name] = null;

                continue;
            }

            $arguments[$name] = $this->hydrateParameter(
                reflection: $reflection,
                parameter: $parameter,
                value: $values[$name],
                context: $context->at($path),
            );
        }

        try {
            return $reflection->newInstanceArgs($arguments);
        } catch (JsonHydrationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new JsonHydrationException(
                $context->path . ': unable to construct JSON target ' . $class . '.',
                previous: $exception,
            );
        }
    }

    public function hydrateCollection(
        string $itemClass,
        mixed $data,
        ?JsonHydrationContext $context = null,
        ?string $factoryClass = null,
    ): array {
        $context ??= new JsonHydrationContext();
        if (!is_array($data) || !array_is_list($data)) {
            throw new JsonHydrationException($context->path . ': expected a JSON list.');
        }

        $result = [];
        foreach ($data as $index => $value) {
            $itemContext = $context->at($context->path . '[' . $index . ']');
            $result[]    = $this->hydrateObject($itemClass, $value, $itemContext, $factoryClass);
        }

        return $result;
    }

    public function hydrateMap(
        string $valueClass,
        mixed $data,
        ?JsonHydrationContext $context = null,
        ?string $factoryClass = null,
    ): array {
        $context ??= new JsonHydrationContext();
        $values = $this->mapValues($data, $context->path);
        $result = [];

        foreach ($values as $key => $value) {
            $itemContext  = $context->at($context->path . '[' . $key . ']');
            $result[$key] = $this->hydrateObject($valueClass, $value, $itemContext, $factoryClass);
        }

        return $result;
    }

    public function normalizeObject(object $value, string $class, string $path = '$'): stdClass
    {
        if (!$value instanceof $class) {
            throw new JsonHydrationException(sprintf('%s: expected %s, got %s.', $path, $class, $value::class));
        }

        $objectId = spl_object_id($value);
        if (isset($this->normalizing[$objectId])) {
            throw new JsonHydrationException($path . ': circular JSON object reference detected.');
        }

        $this->normalizing[$objectId] = true;
        try {
            if ($value instanceof JsonSerializable) {
                $serialized = $value->jsonSerialize();
                $values     = $this->objectValues($serialized, $path);

                return $this->normalizedObject($values, $path);
            }

            $reflection = new ReflectionClass($value);

            $constructor = $reflection->getConstructor();
            if ($constructor === null) {
                return new stdClass();
            }

            $normalized = new stdClass();
            foreach ($constructor->getParameters() as $parameter) {
                $name = $parameter->getName();
                if (!$reflection->hasProperty($name)) {
                    throw new JsonHydrationException(
                        $path . ': constructor parameter $' . $name . ' has no matching property; implement JsonSerializable.',
                    );
                }

                $property = $reflection->getProperty($name);
                if (!$property->isInitialized($value)) {
                    throw new JsonHydrationException($path . '.' . $name . ' is not initialized.');
                }

                $normalized->{$name} = $this->normalizeParameter(
                    reflection: $reflection,
                    parameter: $parameter,
                    value: $property->getValue($value),
                    path: $path . '.' . $name,
                );
            }

            return $normalized;
        } finally {
            unset($this->normalizing[$objectId]);
        }
    }

    public function normalizeCollection(array $value, string $itemClass, string $path = '$'): array
    {
        if (!array_is_list($value)) {
            throw new JsonHydrationException($path . ': expected a PHP list.');
        }

        $result = [];
        foreach ($value as $index => $item) {
            if (!is_object($item)) {
                throw new JsonHydrationException($path . '[' . $index . ']: expected an object.');
            }

            $result[] = $this->normalizeObject($item, $itemClass, $path . '[' . $index . ']');
        }

        return $result;
    }

    public function normalizeMap(array $value, string $valueClass, string $path = '$'): stdClass
    {
        if ($value !== [] && array_is_list($value)) {
            throw new JsonHydrationException($path . ': expected a PHP associative map.');
        }

        $result = new stdClass();
        foreach ($value as $key => $item) {
            if (!is_object($item)) {
                throw new JsonHydrationException($path . '[' . $key . ']: expected an object.');
            }

            $result->{(string) $key} = $this->normalizeObject($item, $valueClass, $path . '[' . $key . ']');
        }

        return $result;
    }

    private function hydrateParameter(
        ReflectionClass $reflection,
        ReflectionParameter $parameter,
        mixed $value,
        JsonHydrationContext $context,
    ): mixed {
        if ($value === null) {
            if ($parameter->allowsNull()) {
                return null;
            }

            throw new JsonHydrationException($context->path . ' cannot be null.');
        }

        [$collectionOf, $mapOf, $factory] = $this->mappingAttributes($reflection, $parameter);

        if ($collectionOf !== null) {
            return $this->hydrateCollection($collectionOf->itemClass, $value, $context, $factory?->factory);
        }

        if ($mapOf !== null) {
            return $this->hydrateMap($mapOf->valueClass, $value, $context, $factory?->factory);
        }

        $type = $this->namedType($parameter->getType(), $context->path);
        if ($type === null || $type->getName() === 'mixed') {
            return $this->toPhpValue($value);
        }

        if ($type->isBuiltin()) {
            return $this->hydrateBuiltin($type->getName(), $value, $context->path);
        }

        $class = $type->getName();
        if (is_a($class, BackedEnum::class, true)) {
            if (!is_int($value) && !is_string($value)) {
                throw new JsonHydrationException($context->path . ': expected a backed enum scalar.');
            }

            try {
                return $class::from($value);
            } catch (Throwable $exception) {
                throw new JsonHydrationException($context->path . ': invalid enum value.', previous: $exception);
            }
        }

        if (is_a($class, UnitEnum::class, true)) {
            if (!is_string($value)) {
                throw new JsonHydrationException($context->path . ': expected an enum case name.');
            }

            foreach ($class::cases() as $case) {
                if ($case->name === $value) {
                    return $case;
                }
            }

            throw new JsonHydrationException($context->path . ': invalid enum case name.');
        }

        return $this->hydrateObject($class, $value, $context, $factory?->factory);
    }

    private function normalizeParameter(
        ReflectionClass $reflection,
        ReflectionParameter $parameter,
        mixed $value,
        string $path,
    ): mixed {
        if ($value === null) {
            if ($parameter->allowsNull()) {
                return null;
            }

            throw new JsonHydrationException($path . ' cannot be null.');
        }

        [$collectionOf, $mapOf] = $this->mappingAttributes($reflection, $parameter);
        if ($collectionOf !== null) {
            if (!is_array($value)) {
                throw new JsonHydrationException($path . ': expected an array.');
            }

            return $this->normalizeCollection($value, $collectionOf->itemClass, $path);
        }

        if ($mapOf !== null) {
            if (!is_array($value)) {
                throw new JsonHydrationException($path . ': expected an array.');
            }

            return $this->normalizeMap($value, $mapOf->valueClass, $path);
        }

        $type = $this->namedType($parameter->getType(), $path);
        if ($type !== null && !$type->isBuiltin() && is_object($value)) {
            $class = $type->getName();
            if ($value instanceof BackedEnum) {
                return $value->value;
            }

            if ($value instanceof UnitEnum) {
                return $value->name;
            }

            return $this->normalizeObject($value, $class, $path);
        }

        return $this->normalizeAny($value, $path);
    }

    private function normalizeAny(mixed $value, string $path): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (is_object($value)) {
            return $this->normalizeObject($value, $value::class, $path);
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->normalizeAny($item, $path . '[' . $key . ']');
            }

            return $result;
        }

        throw new JsonHydrationException($path . ': unsupported JSON value.');
    }

    private function hydrateBuiltin(string $type, mixed $value, string $path): mixed
    {
        return match ($type) {
            'string' => is_string($value) ? $value : throw new JsonHydrationException($path . ': expected string.'),
            'int'    => is_int($value) ? $value : throw new JsonHydrationException($path . ': expected int.'),
            'float'  => is_float($value) || is_int($value) ? (float) $value : throw new JsonHydrationException($path . ': expected float.'),
            'bool'   => is_bool($value) ? $value : throw new JsonHydrationException($path . ': expected bool.'),
            'array'  => is_array($value) || $value instanceof stdClass
                ? $this->toPhpValue($value)
                : throw new JsonHydrationException($path . ': expected array.'),
            'object' => $value instanceof stdClass ? $value : throw new JsonHydrationException($path . ': expected object.'),
            default  => throw new JsonHydrationException($path . ': unsupported PHP type ' . $type . '.'),
        };
    }

    private function namedType(?ReflectionType $type, string $path): ?ReflectionNamedType
    {
        if ($type === null) {
            return null;
        }

        if ($type instanceof ReflectionUnionType) {
            throw new JsonHydrationException($path . ': union JSON types are not supported; configure #[JsonFactory].');
        }

        if (!$type instanceof ReflectionNamedType) {
            throw new JsonHydrationException($path . ': unsupported reflected JSON type.');
        }

        return $type;
    }

    /**
     * @return array{?JsonCollectionOf, ?JsonMapOf, ?JsonFactory}
     */
    private function mappingAttributes(ReflectionClass $reflection, ReflectionParameter $parameter): array
    {
        $property = $reflection->hasProperty($parameter->getName())
            ? $reflection->getProperty($parameter->getName())
            : null;

        $collectionOf = $this->attribute($parameter, $property, JsonCollectionOf::class);
        $mapOf        = $this->attribute($parameter, $property, JsonMapOf::class);
        $factory      = $this->attribute($parameter, $property, JsonFactory::class);

        if ($collectionOf !== null && $mapOf !== null) {
            throw new JsonHydrationException(
                '$' . $parameter->getName() . ' cannot use both JsonCollectionOf and JsonMapOf.',
            );
        }

        if ($collectionOf !== null || $mapOf !== null) {
            $type = $parameter->getType();
            if (!$type instanceof ReflectionNamedType || $type->getName() !== 'array') {
                throw new JsonHydrationException(
                    '$' . $parameter->getName() . ' must be typed as array for JsonCollectionOf/JsonMapOf.',
                );
            }
        }

        return [$collectionOf, $mapOf, $factory];
    }

    /**
     * @template T of object
     * @param class-string<T> $attributeClass
     * @return T|null
     */
    private function attribute(
        ReflectionParameter $parameter,
        ?ReflectionProperty $property,
        string $attributeClass,
    ): ?object {
        $attributes = $parameter->getAttributes($attributeClass);
        if ($attributes === [] && $property !== null) {
            $attributes = $property->getAttributes($attributeClass);
        }

        return ($attributes[0] ?? null)?->newInstance();
    }

    /**
     * @return array<string, mixed>
     */
    private function objectValues(mixed $data, string $path): array
    {
        if ($data instanceof stdClass) {
            return get_object_vars($data);
        }

        if (is_array($data) && ($data === [] || !array_is_list($data))) {
            return $data;
        }

        throw new JsonHydrationException($path . ': expected a JSON object.');
    }

    /**
     * @return array<array-key, mixed>
     */
    private function mapValues(mixed $data, string $path): array
    {
        if ($data instanceof stdClass) {
            return get_object_vars($data);
        }

        if (is_array($data) && ($data === [] || !array_is_list($data))) {
            return $data;
        }

        throw new JsonHydrationException($path . ': expected a JSON object map.');
    }

    /**
     * @param array<string, mixed> $values
     */
    private function normalizedObject(array $values, string $path): stdClass
    {
        $result = new stdClass();

        foreach ($values as $key => $value) {
            $result->{$key} = $this->normalizeAny($value, $path . '.' . $key);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function toPhpArray(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[$key] = $this->toPhpValue($value);
        }

        return $result;
    }

    private function toPhpValue(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            return $this->toPhpArray(get_object_vars($value));
        }

        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $result[$key] = $this->toPhpValue($item);
            }

            return $result;
        }

        return $value;
    }
}
