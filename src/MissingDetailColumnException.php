<?php

namespace ServiceTo;

use RuntimeException;

class MissingDetailColumnException extends RuntimeException
{
    /**
     * Create a new exception instance.
     *
     * @param  string  $model
     * @param  string  $table
     * @return static
     */
    public static function forModel(string $model, string $table)
    {
        return new static(
            "The model [{$model}] uses the UsesDetail trait but the table [{$table}] does not have a 'detail' column. " .
            "Please add a 'detail' JSON column to the table migration: \$table->json('detail');"
        );
    }
}
