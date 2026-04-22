<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting;

use InvalidArgumentException;
use PhpSoftBox\DataCasting\Contracts\JsonValueFactoryInterface;
use PhpSoftBox\DataCasting\Contracts\JsonValueFactoryResolverInterface;
use ReflectionClass;
use ReflectionException;

use function is_a;

final class JsonValueFactoryResolver implements JsonValueFactoryResolverInterface
{
    public function resolve(string $factoryClass): JsonValueFactoryInterface
    {
        if (!is_a($factoryClass, JsonValueFactoryInterface::class, true)) {
            throw new InvalidArgumentException(
                'JSON factory must implement ' . JsonValueFactoryInterface::class . ': ' . $factoryClass,
            );
        }

        try {
            $reflection = new ReflectionClass($factoryClass);

            $constructor = $reflection->getConstructor();
            if ($constructor !== null && $constructor->getNumberOfRequiredParameters() > 0) {
                throw new InvalidArgumentException(
                    'JSON factory requires dependencies and must be provided by a custom resolver: ' . $factoryClass,
                );
            }

            return $reflection->newInstance();
        } catch (ReflectionException $exception) {
            throw new InvalidArgumentException('Unable to instantiate JSON factory: ' . $factoryClass, previous: $exception);
        }
    }
}
