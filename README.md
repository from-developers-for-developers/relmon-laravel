# relmon-laravel

Laravel binding for the framework-agnostic RelMon PHP SDK.

## Install

This package targets PHP 8.1+ and Laravel 10, 11, or 12.

```bash
composer require from-developers-for-developers/relmon-laravel
```

The package auto-discovers:

- `FromDevelopersForDevelopers\RelMonLaravel\RelMonServiceProvider`
- `RelMon` facade alias

## Usage

```php
use FromDevelopersForDevelopers\RelMonLaravel\Facades\RelMon;

$relmon = RelMon::build([
    'protocol' => 'relmon@1.0.0/3',
    'net' => '100.00',
    'gross' => '121.00',
    'tax' => '21.00',
]);
```

You can also resolve the injectable service directly:

```php
$relmon = app('relmon')->build($payload);
```

Or inject it through the constructor:

```php
use FromDevelopersForDevelopers\RelMonLaravel\Contracts\RelMonServiceContract;

final class InvoiceService
{
    public function __construct(private RelMonServiceContract $relmon)
    {
    }

    public function total(array $payload)
    {
        return $this->relmon->build($payload);
    }
}
```

The `relmon` container key is kept for Laravel conventions and facade access. The underlying service is a singleton wrapper, but it is stateless: config, defaults, and custom parser instances are resolved inside each `build()` call. That means injecting the service does not eagerly resolve parsers; parser resolution happens only when `build()` is called.

Validate incoming payloads with the package rule:

```php
use FromDevelopersForDevelopers\RelMon\Enum\Format;
use FromDevelopersForDevelopers\RelMonLaravel\Rules\RelMonPayload;

$request->validate([
    'relmon' => [
        'required',
        new RelMonPayload(),
    ],
]);
```

Or, when the field is a JSON string and you want explicit defaults:

```php
$request->validate([
    'relmon' => [
        'required',
        new RelMonPayload(format: Format::JSON_STRING, defaults: ['taxRate' => '21.00']),
    ],
]);
```

Map virtual Eloquent attributes across multiple columns with the cast:

```php
use FromDevelopersForDevelopers\RelMonLaravel\Casts\RelMonColumnsCast;

protected $casts = [
    'amount' => RelMonColumnsCast::class .
        ':net=net_amount,gross=gross_amount,tax=tax_amount,taxRate=tax_rate,precision=precision,mode=auto',
];
```

`mode=auto` reads by hydrated PHP value types and writes minor integers by default. Integers are treated as minor values, while decimal strings and floats are treated as decimal values. Integer-like strings such as `'10000'` are rejected in `auto` mode because they are ambiguous; use `mode=minors` or `mode=decimal` explicitly for those columns. Use `mode=decimal` when your database columns should be written back as formatted decimal strings.

Publish the config with:

```bash
php artisan vendor:publish --tag=relmon-config
```

## Configuration

```php
return [
    'default_format' => env('RELMON_DEFAULT_FORMAT', \FromDevelopersForDevelopers\RelMon\Enum\Format::AUTO),

    'protocol_identifier' => env('RELMON_PROTOCOL_IDENTIFIER', 'relmon@1.0.0/3'),

    'defaults' => [
        'unit' => env('RELMON_DEFAULT_UNIT'),
        'scope' => env('RELMON_DEFAULT_SCOPE'),
        'roundingMode' => env('RELMON_DEFAULT_ROUNDING_MODE'),
        'roundingApplication' => env('RELMON_DEFAULT_ROUNDING_APPLICATION'),
        'taxRate' => env('RELMON_DEFAULT_TAX_RATE'),
    ],

    'custom_parsers' => [
        App\RelMon\CsvRelMonParser::class,
    ],
];
```

Equivalent `.env` example:

```dotenv
RELMON_DEFAULT_FORMAT=auto
RELMON_PROTOCOL_IDENTIFIER=relmon@1.0.0/3
RELMON_DEFAULT_UNIT=EUR
RELMON_DEFAULT_SCOPE=r
RELMON_DEFAULT_ROUNDING_MODE=heven
RELMON_DEFAULT_ROUNDING_APPLICATION=tax
RELMON_DEFAULT_TAX_RATE=21.00
RELMON_CUSTOM_PARSERS="App\RelMon\CsvRelMonParser,App\RelMon\LegacyRelMonParser"
```

`protocol_identifier` is used by the Eloquent cast when it needs to build a RelMon payload from decimal database columns. Minors-mode reads construct `RelMonObject` directly because those database values are assumed to be already normalized integer minors; decimal-mode reads go through the service so the SDK can validate, infer precision, derive values, and normalize the object.
