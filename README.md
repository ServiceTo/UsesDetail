# UsesDetail

A Laravel trait that enables dynamic model attributes using a JSON detail column.

## Installation

You can install the package via composer:

```bash
composer require service-to/uses-detail
```

## Usage

1. Add the `detail` column to your migration:

```php
Schema::create('your_table', function (Blueprint $table) {
    $table->id();
    $table->json('detail');
    $table->timestamps();
});
```

2. Use the trait in your model:

```php
use ServiceTo\UsesDetail;

class YourModel extends Model
{
    use UsesDetail;
}
```

## Features

- Automatically stores non-database columns in a JSON detail column
- Provides UUID generation for models
- Enables searching within the detail column using the `detail` scope
- Supports merging objects into the model
- Handles JSON encoding/decoding automatically

## Methods

### `scopeDetail($query, $column, $operator = null, $value = null, $boolean = 'and')`

Search within the detail column:

```php
YourModel::detail('key', '=', 'value')->get();
```

### `merge($obj)`

Merge an object or JSON string into the model:

```php
$model->merge($object);
$model->merge('{"key": "value"}');
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information. 