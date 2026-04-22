<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Handlers;

use InvalidArgumentException;
use PhpSoftBox\DataCasting\Contracts\TypeHandlerInterface;

use function class_exists;
use function is_object;
use function is_scalar;
use function is_string;

final class StoragePathHandler implements TypeHandlerInterface
{
    private const STORAGE_CLASS      = 'PhpSoftBox\\Storage\\Storage';
    private const STORAGE_PATH_CLASS = 'PhpSoftBox\\Storage\\StoragePath';

    public function supports(string $type): bool
    {
        return $type === 'storage_path';
    }

    public function castTo(mixed $value, array $options = []): ?string
    {
        $storagePathClass = self::STORAGE_PATH_CLASS;
        if (class_exists($storagePathClass) && is_object($value) && $value instanceof $storagePathClass) {
            $path = $value->path();

            return $path !== '' ? $path : null;
        }

        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return $value !== '' ? $value : null;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        $path = is_string($value) ? $value : (is_scalar($value) ? (string) $value : null);
        if ($path === null || $path === '') {
            return null;
        }

        $storageClass     = self::STORAGE_CLASS;
        $storagePathClass = self::STORAGE_PATH_CLASS;

        if (!class_exists($storageClass) || !class_exists($storagePathClass)) {
            return $path;
        }

        if (is_object($value) && $value instanceof $storagePathClass) {
            return $value;
        }

        $storage = $options['storage'] ?? null;
        if (!($storage instanceof $storageClass)) {
            throw new InvalidArgumentException('storage_path cast requires Storage instance in options.');
        }

        $disk = $options['disk'] ?? null;
        $disk = is_string($disk) && $disk !== '' ? $disk : null;

        return new $storagePathClass($storage, $path, $disk);
    }
}
