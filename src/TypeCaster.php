<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting;

use InvalidArgumentException;
use PhpSoftBox\DataCasting\Contracts\TypeCasterInterface;
use PhpSoftBox\DataCasting\Contracts\TypeHandlerInterface;

use function array_key_exists;
use function array_unshift;
use function array_values;
use function is_a;
use function is_array;

/**
 * Универсальный runtime type-caster.
 */
final class TypeCaster implements TypeCasterInterface
{
    /**
     * @param list<TypeHandlerInterface> $handlers
     */
    public function __construct(array $handlers)
    {
        $this->handlers = array_values($handlers);
    }

    /**
     * @var list<TypeHandlerInterface>
     */
    private array $handlers;

    /**
     * Экземпляры handler'ов, переданных в castTo()/castFrom() как class-string.
     *
     * @var array<class-string<TypeHandlerInterface>, TypeHandlerInterface>
     */
    private array $handlerInstances = [];

    public function handlers(): array
    {
        return $this->handlers;
    }

    public function registerHandler(TypeHandlerInterface $handler): void
    {
        array_unshift($this->handlers, $handler);
    }

    public function castArray(array $config, array $data, array $options = []): array
    {
        $result = $data;

        foreach ($config as $key => $type) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            $fieldOptions = $options[$key] ?? [];
            $result[$key] = $this->castFrom($type, $data[$key], is_array($fieldOptions) ? $fieldOptions : []);
        }

        return $result;
    }

    public function castTo(string $type, mixed $value, array $options = []): int|float|string|bool|null
    {
        // Если передали class-string handler'а — применяем его напрямую.
        if (is_a($type, TypeHandlerInterface::class, true)) {
            return $this->handlerInstance($type)->castTo($value, $options);
        }

        return $this->resolveHandler($type)->castTo($value, $this->withType($type, $options));
    }

    public function castFrom(string $type, mixed $value, array $options = []): mixed
    {
        // Если передали class-string handler'а — применяем его напрямую.
        if (is_a($type, TypeHandlerInterface::class, true)) {
            return $this->handlerInstance($type)->castFrom($value, $options);
        }

        return $this->resolveHandler($type)->castFrom($value, $this->withType($type, $options));
    }

    /**
     * Handler, обслуживающий несколько типов (например, datetime/date/time), должен знать,
     * для какого типа его вызвали, даже если опция `type` не передана явно.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function withType(string $type, array $options): array
    {
        $options['type'] ??= $type;

        return $options;
    }

    /**
     * @param class-string<TypeHandlerInterface> $class
     */
    private function handlerInstance(string $class): TypeHandlerInterface
    {
        return $this->handlerInstances[$class] ??= new $class();
    }

    private function resolveHandler(string $type): TypeHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof TypeHandlerInterface && $handler->supports($type)) {
                return $handler;
            }
        }

        throw new InvalidArgumentException('No type handler registered for type: ' . $type);
    }
}
