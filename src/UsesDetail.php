<?php

namespace ServiceTo;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use ErrorException;
use TypeError;
use stdClass;

trait UsesDetail
{
    public static function bootUsesDetail()
    {
        static::saving(function ($model) {
            $columns = Cache::remember("schema." . $model->getTable(), 300, function () use ($model) {
                return Schema::getColumnListing($model->getTable());
            });

            $detail = new stdClass();
            foreach ($model->getAttributes() as $key => $value) {
                if (!in_array($key, $columns)) {
                    $detail->{$key} = $value;
                    unset($model->{$key});
                }
            }
            $model->detail = json_encode($detail);
        });

        static::saved(function ($model) {
            $detail = json_decode($model->detail ?? '{}');
            if ($detail) {
                foreach ($detail as $key => $value) {
                    $model->{$key} = $value;
                }
            }
            // remove detail from model since it's loaded into the attributes now
            unset($model->detail);
        });

        static::retrieved(function ($model) {
            $detail = json_decode($model->detail ?: '{}');
            if ($detail) {
                foreach ($detail as $key => $value) {
                    $model->{$key} = $value;
                }
            }
            // remove detail from model since it's loaded into the attributes now
            unset($model->detail);
        });
    }

    // adds a uuid when creating an instance of this model
    protected function initializeUsesDetail(): void
    {
        $this->uuid = Str::uuid()->toString();
    }

    // adds automatic uuid lookup in the detail parameter if the id is 36 characters long
    public static function find($id, array $columns = ['*'])
    {
        if (is_array($id) || $id instanceof Arrayable) {
            return self::findMany($id, $columns);
        }

        if (is_string($id) && strlen($id) === 36) {
            return self::detail('uuid', '=', $id)->first($columns);
        }

        return self::whereKey($id)->first($columns);
    }

    // use like you would use "where", prepends "detail->" to the column automatically
    // intelligently checks if the column is in the schema and uses regular where if it is
    public function scopeDetail($query, string $column, $operator = null, $value = null, string $boolean = 'and')
    {
        $model = $query->getModel();
        $columns = Cache::remember("schema." . $model->getTable(), 300, function () use ($model) {
            return Schema::getColumnListing($model->getTable());
        });

        // If the column exists in the schema, use regular where
        if (in_array($column, $columns)) {
            return $query->where($column, $operator, $value, $boolean);
        }

        // Otherwise, use the JSON path query
        return $query->where('detail->' . $column, $operator, $value, $boolean);
    }

    // take all the properties from object and merge them with myself
    public function merge($obj): bool
    {
        if (is_string($obj) && $this->isJson($obj)) {
            $obj = json_decode($obj);
        }
        
        if (!is_object($obj) && !is_array($obj)) {
            return false;
        }
        
        foreach ((array)$obj as $key => $value) {
            // Clean property names that have null bytes from object-to-array casting
            $cleanKey = $this->cleanPropertyName($key);

            if ($this->shouldSkipAttribute($cleanKey)) {
                continue;
            }
            $this->{$cleanKey} = $value;
        }
        
        return true;
    }

    public function isJson($string): bool
    {
        if (!is_string($string)) {
            return false;
        }
        
        try {
            json_decode($string);
            return json_last_error() === JSON_ERROR_NONE;
        } catch (ErrorException $ee) {
            return false;
        }
    }

    private function cleanPropertyName(string $key): string
    {
        // Remove null bytes and class prefixes from property names
        // Private properties: \0ClassName\0propertyName -> propertyName
        // Protected properties: \0*\0propertyName -> propertyName
        return preg_replace('/^\0.*?\0/', '', $key);
    }

    private function shouldSkipAttribute(string $key): bool
    {
        // Skip primary key
        if ($key === $this->primaryKey) {
            return true;
        }

        // Skip timestamps if enabled
        if ($this->timestamps && in_array($key, ['created_at', 'updated_at'])) {
            return true;
        }

        // Skip casted attributes
        if (is_array($this->casts) && array_key_exists($key, $this->casts)) {
            return true;
        }

        return false;
    }
} 