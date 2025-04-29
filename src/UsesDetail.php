<?php

namespace ServiceTo;

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
            $detail = json_decode($model->detail);
            foreach ($detail as $key => $value) {
                $model->{$key} = $value;
            }
            // remove detail from model since it's loaded into the attributes now
            unset($model->detail);
        });

        static::retrieved(function ($model) {
            $detail = json_decode($model->detail ?: "{}");
            foreach ($detail as $key => $value) {
                $model->{$key} = $value;
            }
            // remove detail from model since it's loaded into the attributes now
            unset($model->detail);
        });
    }

    // adds a uuid when creating an instance of this model
    protected function initializeUsesDetail()
    {
        $this->uuid = Str::uuid()->toString();
    }

    // adds automatic uuid lookup in the detail parameter if the id is 36 characters long
    public static function find($id, $columns = ["*"])
    {
        if (is_array($id) || $id instanceof Arrayable) {
            return self::findMany($id, $columns);
        }

        if (strlen($id) == 36) {
            return self::detail("uuid", "=", $id)->first($columns);
        }

        return self::whereKey($id)->first($columns);
    }

    // use like you would use "where", prepends "detail->" to the column automatically
    public function scopeDetail($query, $column, $operator = null, $value = null, $boolean = 'and')
    {
        return $query->where("detail->" . $column, $operator, $value, $boolean);
    }

    // take all the properties from object and merge them with myself
    public function merge($obj)
    {
        if (is_string($obj) && $this->isJson($obj)) {
            $obj = json_decode($obj);
        }
        if (is_object($obj) || is_array($obj)) {
            foreach ((array)$obj as $key => $value) {
                // don't overwrite timestamps, casts or the key
                if ($key != $this->primaryKey && ($this->timestamps && ($key != "created_at" && $key != "updated_at")) && !(is_array($this->casts) && array_key_exists($key, $this->casts))) {
                    $this->{$key} = $value;
                }
            }
            return true;
        }
        return false;
    }

    public function isJson($string)
    {
        try {
            json_decode($string);
            return json_last_error() === JSON_ERROR_NONE;
        } catch (ErrorException $ee) {
            return false;
        }
    }
} 