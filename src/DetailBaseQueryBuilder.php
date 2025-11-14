<?php

namespace ServiceTo;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DetailBaseQueryBuilder extends Builder
{
    /**
     * The model instance being queried.
     *
     * @var \Illuminate\Database\Eloquent\Model|null
     */
    protected $model;

    /**
     * Set the model being queried.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return $this
     */
    public function setModel($model)
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Get the model being queried.
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Get the cached schema columns for the model's table.
     *
     * @return array
     */
    protected function getCachedSchemaColumns()
    {
        if (!$this->model) {
            return [];
        }

        return Cache::remember("schema." . $this->from, 300, function () {
            return Schema::getColumnListing($this->from);
        });
    }

    /**
     * Check if a column exists in the schema.
     *
     * @param  string  $column
     * @return bool
     */
    protected function columnExistsInSchema($column)
    {
        return in_array($column, $this->getCachedSchemaColumns());
    }

    /**
     * Resolve the column name to either a schema column or detail JSON path.
     *
     * @param  string  $column
     * @return string
     */
    protected function resolveColumn($column)
    {
        if (!$this->model) {
            return $column;
        }

        // If column is already a detail JSON path, don't resolve it again
        if (str_starts_with($column, 'detail->')) {
            return $column;
        }

        // Extract just the column name (strip table qualifier if present)
        $columnName = $column;
        $tableName = null;

        if (str_contains($column, '.')) {
            $parts = explode('.', $column);
            $tableName = $parts[0];
            $columnName = $parts[1];

            // If the table qualifier is NOT the model's table, this is a reference to another
            // table (like a pivot table). Pass it through as-is.
            if ($tableName !== $this->from) {
                return $column;
            }
            // If table matches model table, continue to check if column needs detail resolution
        }

        if (!$this->columnExistsInSchema($columnName)) {
            // Column doesn't exist in schema, need to use detail column
            $columns = $this->getCachedSchemaColumns();
            if (!in_array('detail', $columns)) {
                // No detail column, pass through as-is
                return $column;
            }
            // Use just the column name (without table qualifier) in the detail JSON path
            return 'detail->' . $columnName;
        }

        // Column exists in schema - return as-is (with qualifier if it was for model table)
        return $column;
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereIn($column, $values, $boolean = 'and', $not = false)
    {
        if (is_string($column) && $this->model) {
            $column = $this->resolveColumn($column);
        }

        return parent::whereIn($column, $values, $boolean, $not);
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotIn($column, $values, $boolean = 'and')
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Add an "or where in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @return $this
     */
    public function orWhereIn($column, $values)
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Add an "or where not in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed  $values
     * @return $this
     */
    public function orWhereNotIn($column, $values)
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    /**
     * Add a "where in" clause for integer values.
     * Laravel's HasMany relationship uses this instead of whereIn for integer keys.
     *
     * @param  string  $column
     * @param  array  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereIntegerInRaw($column, $values, $boolean = 'and', $not = false)
    {
        if (is_string($column) && $this->model) {
            $column = $this->resolveColumn($column);
        }

        return parent::whereIntegerInRaw($column, $values, $boolean, $not);
    }

    /**
     * Add a "where not in" clause for integer values.
     *
     * @param  string  $column
     * @param  array  $values
     * @param  string  $boolean
     * @return $this
     */
    public function whereIntegerNotInRaw($column, $values, $boolean = 'and')
    {
        return $this->whereIntegerInRaw($column, $values, $boolean, true);
    }

    /**
     * Add an "or where in" clause for integer values.
     *
     * @param  string  $column
     * @param  array  $values
     * @return $this
     */
    public function orWhereIntegerInRaw($column, $values)
    {
        return $this->whereIntegerInRaw($column, $values, 'or');
    }

    /**
     * Add an "or where not in" clause for integer values.
     *
     * @param  string  $column
     * @param  array  $values
     * @return $this
     */
    public function orWhereIntegerNotInRaw($column, $values)
    {
        return $this->whereIntegerNotInRaw($column, $values, 'or');
    }
}
