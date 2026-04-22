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

    public function handlers(): array
    {
        return $this->handlers;
    }

    public function registerHandler(TypeHandlerInterface $handler): void
    {
        array_unshift($this->handlers, $handler);
    }

    public function castArray(array $config, array $data): array
    {
        $result = $data;

        foreach ($config as $key => $typeOrHandler) {
            if (!array_key_exists($key, $data)) {
                continue;
            }

            if (is_a($typeOrHandler, TypeHandlerInterface::class, true)) {
                $handler = new $typeOrHandler();

                $result[$key] = $handler->castFrom($data[$key]);
                continue;
            }

            $result[$key] = $this->castFrom($typeOrHandler, $data[$key]);
        }

        return $result;
    }

    public function castTo(string $type, mixed $value, array $options = []): int|float|string|bool|null
    {
        // Если передали class-string handler'а — применяем его напрямую.
        if (is_a($type, TypeHandlerInterface::class, true)) {
            return new $type()->castTo($value, $options);
        }

        return $this->resolveHandler($type)->castTo($value, $options);
    }

    public function castFrom(string $type, mixed $value, array $options = []): mixed
    {
        // Если передали class-string handler'а — применяем его напрямую.
        if (is_a($type, TypeHandlerInterface::class, true)) {
            return new $type()->castFrom($value, $options);
        }

        return $this->resolveHandler($type)->castFrom($value, $options);
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
