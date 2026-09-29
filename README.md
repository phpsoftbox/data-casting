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

### castArray

`castArray()` приводит поля массива через `castFrom()`. Третьим аргументом можно передать опции
handler'а для каждого поля:

```php
$row = $caster->castArray(
    ['created' => 'date', 'id' => 'uuid'],
    ['created' => '22.04.2026', 'id' => '123e4567-e89b-12d3-a456-426655440000'],
    ['created' => ['format_from' => '!d.m.Y']],
);
```

Handler, переданный как class-string (`$caster->castFrom(MyHandler::class, $value)`), создаётся один раз
и переиспользуется. При вызове по имени типа `TypeCaster` передаёт в handler опцию `type`
(если она не задана явно), поэтому `castTo('date', $dateTime)` без опций возвращает `Y-m-d`.

## Options

Все поля объектов опций (`DatetimeCastOptions`, `JsonCastOptions`, `MoneyCastOptions`, …) nullable,
`null` означает «не задано». `TypeCastOptionsManager::resolve()` собирает опции в порядке приоритета:

1. заданные (не-null) поля объекта опций колонки (`#[Column(options: ...)]`);
2. дефолты типа, зарегистрированные через `registerDefaults()`;
3. дефолты самого handler'а (например, класс DateTime из `DefaultTypeCasterFactory(dateTimeClass: ...)`).

Поэтому `new DatetimeCastOptions(formatTo: 'Y-m-d H:i:s')` на колонке не сбрасывает класс DateTime,
настроенный в фабрике или в `registerDefaults('datetime', ...)`.

Встроенные дефолты менеджера задают только форматы date/time и класс `DatePoint` для `*_point`-типов;
остальные значения по умолчанию описаны в handler'ах ниже.

## Types

### datetime / date / time

- `datetime`: запись в формате `DateTimeInterface::ATOM`, чтение через конструктор класса
  (`DateTimeImmutable` или класс из `DefaultTypeCasterFactory`) либо через `format_from`.
- `date`: запись `Y-m-d`, чтение `!Y-m-d` — время всегда `00:00:00`.
- `time`: запись `H:i:s`, чтение `!H:i:s` — дата всегда `1970-01-01`.
- `date_point`, `day_point`, `time_point`: то же для `PhpSoftBox\Clock\DatePoint`. Для `date_point` формат чтения по
  умолчанию не задан — значение разбирается свободно, поэтому читаются и `DATETIME`, и `TIMESTAMP` Postgres с дробной
  частью секунд и смещением. Явный `format_from` строгий: несовпадение — исключение.

Форматы с `!` обнуляют незаданные части, поэтому значения date/time не зависят от текущего момента
и корректно сравниваются при dirty-check. Если значение не подходит под формат по умолчанию,
используется разбор через конструктор; явно заданный `format_from` fallback не допускает.

### json

По умолчанию невалидный JSON и JSON-скаляр (`"text"`, `5`, `true`) вместо объекта/массива приводят к
`InvalidArgumentException`. JSON-литерал `null` читается как `null`.

Политику можно изменить опцией `invalidJson` (`JsonInvalidPolicy`):

| Политика | Результат |
|----------|-----------|
| `Throw` (по умолчанию) | `InvalidArgumentException` |
| `Null` | `null` |
| `Empty` | `[]` (для target/collection/map всё равно исключение) |

`Null` и `Empty` опасны вместе с ORM: при следующем сохранении сущности исходное значение колонки
будет перезаписано `NULL` или `'[]'`. Используйте их только осознанно, например для колонок,
где потеря повреждённого значения допустима:

```php
#[Column(type: 'json', options: new JsonCastOptions(invalidJson: JsonInvalidPolicy::Empty))]
public array $meta;
```

При маппинге JSON в объект отсутствующее поле получает значение по умолчанию параметра конструктора,
затем `null` для nullable-параметра без дефолта; иначе — ошибка «`$.field` is required». Поэтому новое
поле с дефолтом в JSON VO не ломает чтение ранее сохранённых строк.

### money

В БД сумма хранится целым числом в минорных единицах (копейках), в PHP — строкой в мажорных
единицах (рублях) с фиксированным `scale` (по умолчанию 2). Семантика определяется только направлением,
а не PHP-типом значения:

| Вызов | Вход | Результат |
|-------|------|-----------|
| `castTo()` (PHP → БД) | `100`, `'100'`, `100.0` — рубли | `10000` |
| `castTo()` | `'12.34'`, `12.34` | `1234` |
| `castFrom()` (БД → PHP) | `10000`, `'10000'` — копейки | `'100.00'` |

- `castTo()` отклоняет значения с большим числом значащих знаков, чем `scale` (`'12.345'`), и float,
  кратчайшее представление которого не укладывается в `scale` (`0.1 + 0.2`). Округления нет.
- `castFrom()` принимает только целые минорные единицы: дробный float или строка с точкой — исключение.
- Суммы вне диапазона PHP int — исключение.

### decimal

Значение возвращается нормализованной строкой без экспоненты: `1e20` → `'100000000000000000000'`,
`'+.5'` → `'0.5'`. Нечисловые строки, `NAN`/`INF` — исключение. Float переводится в строку по
кратчайшему точному представлению (`0.1 + 0.2` → `'0.30000000000000004'`).

- `scale` — дополняет дробную часть нулями до `scale`; лишние значащие знаки — исключение (без округления);
- `trimTrailingZeros` — убирает хвостовые нули дробной части (после применения `scale`).

### bool

`castTo()` и `castFrom()` используют одни и те же списки `trueValues`/`falseValues` (строки
сравниваются без учёта регистра и внешних пробелов): `castTo('false')` даёт `false`. Нераспознанное
значение при `strict: true` — исключение, иначе `(bool) $value`.

### pg_array

Литералы массивов PostgreSQL разбираются и собираются по правилам PostgreSQL:

- пустые строки, `NULL`, значения с пробелами, запятыми, фигурными скобками, `"` и `\` берутся
  в кавычки, `"` и `\` экранируются: `['', 'NULL', null]` → `{"","NULL",NULL}`;
- при чтении `NULL` без кавычек — `null`, `"NULL"` — строка; у элементов без кавычек отбрасываются
  только внешние пробелы;
- многомерные массивы: `{{1,2},{3,4}}` ↔ `[[1, 2], [3, 4]]`; префикс `[0:1]={...}` пропускается;
- элементы `DateTimeInterface`, `BackedEnum` и `Stringable` записываются строкой.

`itemType` приводит элементы при чтении: `string`, `int`, `float` (включая `NaN`/`Infinity`), `bool`,
`uuid` (`UuidInterface`), `datetime` (`DateTimeImmutable`). Значение, не подходящее под тип, и
неизвестный `itemType` — исключение.

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
