<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Tests\Fixtures;

use PhpSoftBox\DataCasting\Handlers\AbstractTypeHandler;

/**
 * Handler, считающий количество созданных экземпляров и возвращающий переданные опции.
 */
final class CountingTypeHandler extends AbstractTypeHandler
{
    public static int $instances = 0;

    public function __construct()
    {
        self::$instances++;
    }

    public function supports(string $type): bool
    {
        return $type === 'counting';
    }

    public function castFrom(mixed $value, array $options = []): mixed
    {
        return ['value' => $value, 'options' => $options];
    }
}
