# DataCasting

## About

`phpsoftbox/data-casting` — компонент приведения данных для PhpSoftBox.

Компонент предоставляет:
- `TypeCaster` и контракты handler-ов;
- набор базовых handler-ов (`int`, `float`, `string`, `json`, `datetime`, `uuid`, `enum`, `money`, `pg_array`, `phone`, `bool`);
- рекурсивный mapping JSON в типизированные объекты, списки и ассоциативные карты объектов;
- `JsonSerializable`, custom hydration и factory/resolver API для полиморфных JSON-значений;
- типизированные options и `TypeCastOptionsManager`.

## Usage

```php
use PhpSoftBox\DataCasting\DefaultTypeCasterFactory;
use PhpSoftBox\DataCasting\Options\TypeCastOptionsManager;

$caster = (new DefaultTypeCasterFactory())->create();
$options = new TypeCastOptionsManager();

$createdAt = $caster->castFrom('datetime', '2026-04-22T10:00:00+03:00', [
    ...$options->resolve('datetime', null),
]);
```

## Validation in JSON factories

`DataCasting` не зависит от HTTP и Validator. Если входной JSON требует бизнес-валидации,
её можно выполнить в `JsonValueFactoryInterface` через независимую `ApiSchema` из компонента
Request:

```php
final readonly class MarketplaceMetadataFactory implements JsonValueFactoryInterface
{
    public function __construct(
        private ValidatorInterface $validator,
        private SoftExceptionReporterInterface $softExceptions,
    ) {
    }

    public function create(array $data, JsonHydrationContext $context): object
    {
        $result = new MarketplaceMetadataSchema($data, $this->validator)->process();

        if ($result->hasErrors()) {
            $this->softExceptions->report(
                MarketplaceSchemaMismatchException::fromResult($result),
            );
        }

        return MarketplaceMetadata::fromArray($result->filteredData());
    }
}
```

Решение о fallback остаётся у фабрики. Например, неизвестные поля можно отрепортить и
проигнорировать, а отсутствие обязательного поля — считать невосстановимой ошибкой.
Так базовый mapper не получает зависимостей от Request/Application и остаётся пригодным
для использования без контейнера.
