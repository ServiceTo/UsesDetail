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

- **Smart Column Detection**: Automatically detects whether columns exist in the database schema or JSON detail column
- **Unified Query Interface**: Use standard Laravel query methods (`where`, `whereIn`, `whereNull`, etc.) for both schema and detail columns
- **Performance Optimized**: Schema information is cached for 5 minutes to minimize database lookups
- **Automatic UUID Generation**: Generates UUIDs for models automatically
- **Flexible Data Merging**: Merge objects or JSON strings into models
- **Fully Backward Compatible**: Existing `detail()` scope continues to work

## Querying Data

### Using Standard Laravel Query Methods (Recommended)

The package intelligently routes queries to either schema columns or the JSON detail column:

```php
// Query any column - works automatically for both schema and detail columns
YourModel::where('name', 'John')->get();
YourModel::where('email', 'like', '%@example.com')->get();

// Use orWhere
YourModel::where('status', 'active')
         ->orWhere('priority', '>', 10)
         ->get();

// Use whereIn / whereNotIn
YourModel::whereIn('status', ['active', 'pending'])->get();
YourModel::whereNotIn('role', ['admin', 'superadmin'])->get();

// Use whereNull / whereNotNull
YourModel::whereNull('deleted_at')->get();
YourModel::whereNotNull('description')->get();

// Use whereBetween / whereNotBetween
YourModel::whereBetween('priority', [1, 10])->get();
YourModel::whereNotBetween('age', [18, 65])->get();

// Chain multiple conditions
YourModel::where('status', 'active')
         ->whereBetween('priority', [5, 15])
         ->whereNotNull('description')
         ->whereIn('category', ['A', 'B'])
         ->get();

// Use orderBy / orderByDesc
YourModel::orderBy('name')->get();
YourModel::orderByDesc('priority')->get();
YourModel::latest()->get();  // Orders by created_at desc
YourModel::oldest()->get();  // Orders by created_at asc

// Combine everything
YourModel::where('status', 'active')
         ->whereBetween('priority', [1, 10])
         ->orderBy('name')
         ->get();
```

### Using the `detail()` Scope (Backward Compatible)

The original `detail()` scope method still works:

```php
YourModel::detail('custom_field', '=', 'value')->get();
YourModel::detail('status', 'active')->get();
```

**Note:** Both methods now use the same intelligent routing under the hood.

## How It Works

When you save a model, the trait automatically:

1. Checks which attributes exist in the database schema
2. Stores schema columns normally
3. Stores non-schema columns in the JSON `detail` column
4. Caches the schema information for 5 minutes

When you query, the custom query builder:

1. Checks the cached schema
2. Routes queries to regular columns if they exist in the schema
3. Routes queries to JSON paths (`detail->column`) if they don't exist in the schema

## Methods

### `merge($obj)`

Merge an object, array, or JSON string into the model:

```php
$model->merge($object);
$model->merge(['key' => 'value']);
$model->merge('{"key": "value"}');
```

### `find($id)`

Enhanced find method with automatic UUID lookup:

```php
// Find by primary key
$model = YourModel::find(1);

// Find by UUID (automatically detected if ID is 36 characters)
$model = YourModel::find('550e8400-e29b-41d4-a716-446655440000');
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information. 