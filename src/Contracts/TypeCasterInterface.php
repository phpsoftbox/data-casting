<?php

declare(strict_types=1);

namespace PhpSoftBox\DataCasting\Contracts;

interface TypeCasterInterface
{
    /**
     * Возвращает список зарегистрированных handler'ов.
     *
     * @return list<TypeHandlerInterface>
     */
    public function handlers(): array;

    /**
     * Регистрирует handler.
     *
     * Handler будет использоваться, если `supports($type)` вернёт true.
     */
    public function registerHandler(TypeHandlerInterface $handler): void;

    /**
     * Приводит значение к типу (в PHP-направлении).
     *
     * @param string|class-string<TypeHandlerInterface> $type
     */
    public function castFrom(string $type, mixed $value, array $options = []): mixed;

    /**
     * Приводит значение к типу (в БД-направлении).
     *
     * @param string|class-string<TypeHandlerInterface> $type
     */
    public function castTo(string $type, mixed $value, array $options = []): int|float|string|bool|null;

    /**
     * Делает cast массива по конфигурации через castFrom().
     *
     * Пример:
     *  $config = ['created' => 'datetime', 'id' => 'uuid', 'custom' => CustomHandler::class]
     *  $data = ['created' => '2022-01-01T00:00:00+00:00', 'id' => '...', 'custom' => '...']
     *
     * @param array<string, string|class-string<TypeHandlerInterface>> $config
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function castArray(array $config, array $data): array;
}
