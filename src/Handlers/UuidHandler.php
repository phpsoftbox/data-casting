<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Handlers;

use InvalidArgumentException;
use PhpSoftBox\DataCasting\Contracts\TypeHandlerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

use function is_string;

final class UuidHandler implements TypeHandlerInterface
{
    public function supports(string $type): bool
    {
        return $type === 'uuid';
    }

    public function castTo(mixed $value, array $options = []): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UuidInterface) {
            return $value->toString();
        }

        if (is_string($value)) {
            return $value;
        }

        throw new InvalidArgumentException('Invalid UUID value.');
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof UuidInterface) {
            return $value;
        }

        if (is_string($value)) {
            return Uuid::fromString($value);
        }

        throw new InvalidArgumentException('Invalid UUID value.');
    }

    public function cast(mixed $value): mixed
    {
        return $this->castFrom($value);
    }
}
