<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Handlers;

use InvalidArgumentException;
use PhpSoftBox\DataCasting\Contracts\TypeHandlerInterface;
use PhpSoftBox\Encryptor\Contracts\EncryptorInterface;

use function is_string;

final readonly class EncryptedHandler implements TypeHandlerInterface
{
    public function __construct(
        private EncryptorInterface $encryptor,
    ) {
    }

    public function supports(string $type): bool
    {
        return $type === 'encrypted';
    }

    public function castTo(mixed $value, array $options = []): ?string
    {
        if ($value === null) {
            return null;
        }

        $key = $options['key'] ?? null;
        if (!is_string($key) || $key === '') {
            throw new InvalidArgumentException('Encrypted cast requires option "key".');
        }

        return $this->encryptor->encrypt((string) $value, $key);
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null) {
            return null;
        }

        $key = $options['key'] ?? null;
        if (!is_string($key) || $key === '') {
            throw new InvalidArgumentException('Encrypted cast requires option "key".');
        }

        if (!is_string($value)) {
            throw new InvalidArgumentException('Encrypted value must be string|null.');
        }

        return $this->encryptor->decrypt($value, $key);
    }
}
