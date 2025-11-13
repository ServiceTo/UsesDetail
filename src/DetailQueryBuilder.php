<?php

namespace ServiceTo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DetailQueryBuilder extends Builder
{
    /**
     * Get the cached schema columns for the model's table.
     *
     * @return array
     */
    protected function getCachedSchemaColumns()
    {
        $model = $this->getModel();
        return Cache::remember("schema." . $model->getTable(), 300, function () use ($model) {
            return Schema::getColumnListing($model->getTable());
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
        // Handle qualified column names like "table_name.column_name"
        // Strip any table qualifier to get just the column name
        if (str_contains($column, '.')) {
            $column = substr($column, strrpos($column, '.') + 1);
        }

        return in_array($column, $this->getCachedSchemaColumns());
    }

    /**
     * Resolve the column name to either a schema column or detail JSON path.
     *
     * @param  string  $column
     * @return string
     * @throws \ServiceTo\MissingDetailColumnException
     */
    protected function resolveColumn($column)
    {
        // Extract just the column name (strip table qualifier if present)
        $columnName = $column;
        if (str_contains($column, '.')) {
            $columnName = substr($column, strrpos($column, '.') + 1);
        }

        if (!$this->columnExistsInSchema($columnName)) {
            // Column doesn't exist in schema, need to use detail column
            $columns = $this->getCachedSchemaColumns();
            if (!in_array('detail', $columns)) {
                throw MissingDetailColumnException::forModel(
                    get_class($this->getModel()),
                    $this->getModel()->getTable()
                );
            }
            // Use just the column name (without table qualifier) in the detail JSON path
            return 'detail->' . $columnName;
        }
        return $column;
    }

    /**
     * Add a basic where clause to the query.
     * Intelligently checks if the column is in the schema and uses regular where if it is,
     * otherwise queries the JSON detail column.
     *
     * @param  \Closure|string|array|\Illuminate\Contracts\Database\Query\Expression  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @param  string  $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        // If it's a closure, array, or Expression, pass through to parent
        if (is_array($column) || $column instanceof \Closure || $column instanceof \Illuminate\Contracts\Database\Query\Expression) {
            return parent::where($column, $operator, $value, $boolean);
        }

        // Check if this is a simple column string
        if (is_string($column)) {
            // Store original column name (may include table qualifier)
            $originalColumn = $column;

            // Extract just the column name for schema checking (strip table qualifier if present)
            $columnName = $column;
            $tableName = null;
            if (str_contains($column, '.')) {
                $parts = explode('.', $column);
                $tableName = $parts[0];
                $columnName = $parts[1];

                // If the table qualifier is NOT the model's table, this is a reference to another
                // table (like a pivot table in a relationship). Pass it through as-is.
                if ($tableName !== $this->getModel()->getTable()) {
                    return parent::where($column, $operator, $value, $boolean);
                }
            }

            // If the column doesn't exist in the schema, query the detail JSON column
            if (!$this->columnExistsInSchema($columnName)) {
                // Check if detail column exists before trying to query it
                $columns = $this->getCachedSchemaColumns();
                if (!in_array('detail', $columns)) {
                    throw MissingDetailColumnException::forModel(
                        get_class($this->getModel()),
                        $this->getModel()->getTable()
                    );
                }
                // Use just the column name (without table qualifier) in the detail JSON path
                return parent::where('detail->' . $columnName, $operator, $value, $boolean);
            }
        }

        // Otherwise, use the regular where (column exists in schema)
        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param  \Closure|string|array  $column
     * @param  mixed  $operator
     * @param  mixed  $value
     * @return $this
     */
    public function orWhere($column, $operator = null, $value = null)
    {
        // If it's a closure, array, or Expression, pass through to parent
        if (is_array($column) || $column instanceof \Closure || $column instanceof \Illuminate\Contracts\Database\Query\Expression) {
            return parent::orWhere($column, $operator, $value);
        }

        // Use our where method with 'or' boolean
        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add a "where between" clause to the query.
     *
     * @param  string  $column
     * @param  iterable  $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereBetween($column, iterable $values, $boolean = 'and', $not = false)
    {
        if (is_string($column)) {
            $column = $this->resolveColumn($column);
        }

        return parent::whereBetween($column, $values, $boolean, $not);
    }

    /**
     * Add a "where not between" clause to the query.
     *
     * @param  string  $column
     * @param  iterable  $values
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotBetween($column, iterable $values, $boolean = 'and')
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    /**
     * Add an "or where between" clause to the query.
     *
     * @param  string  $column
     * @param  iterable  $values
     * @return $this
     */
    public function orWhereBetween($column, iterable $values)
    {
        return $this->whereBetween($column, $values, 'or');
    }

    /**
     * Add an "or where not between" clause to the query.
     *
     * @param  string  $column
     * @param  iterable  $values
     * @return $this
     */
    public function orWhereNotBetween($column, iterable $values)
    {
        return $this->whereNotBetween($column, $values, 'or');
    }

    /**
     * Add a "where null" clause to the query.
     *
     * @param  string|array  $columns
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereNull($columns, $boolean = 'and', $not = false)
    {
        if (is_string($columns)) {
            $columns = $this->resolveColumn($columns);
        } elseif (is_array($columns)) {
            $columns = array_map(function($column) {
                return is_string($column) ? $this->resolveColumn($column) : $column;
            }, $columns);
        }

        return parent::whereNull($columns, $boolean, $not);
    }

    /**
     * Add a "where not null" clause to the query.
     *
     * @param  string|array  $columns
     * @param  string  $boolean
     * @return $this
     */
    public function whereNotNull($columns, $boolean = 'and')
    {
        return $this->whereNull($columns, $boolean, true);
    }

    /**
     * Add an "or where null" clause to the query.
     *
     * @param  string|array  $columns
     * @return $this
     */
    public function orWhereNull($columns)
    {
        return $this->whereNull($columns, 'or');
    }

    /**
     * Add an "or where not null" clause to the query.
     *
     * @param  string|array  $columns
     * @return $this
     */
    public function orWhereNotNull($columns)
    {
        return $this->whereNotNull($columns, 'or');
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
        if (is_string($column)) {
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
     * Add an "order by" clause to the query.
     * Intelligently routes to schema or detail column.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Query\Expression|string  $column
     * @param  string  $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        // If it's not a simple string, pass through to parent
        if (!is_string($column)) {
            return parent::orderBy($column, $direction);
        }

        // Resolve column name
        $column = $this->resolveColumn($column);
        return parent::orderBy($column, $direction);
    }

    /**
     * Add a descending "order by" clause to the query.
     *
     * @param  \Closure|\Illuminate\Database\Query\Builder|\Illuminate\Database\Query\Expression|string  $column
     * @return $this
     */
    public function orderByDesc($column)
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function latest($column = null)
    {
        if (is_null($column)) {
            $column = $this->getModel()->getCreatedAtColumn() ?? 'created_at';
        }

        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  string  $column
     * @return $this
     */
    public function oldest($column = null)
    {
        if (is_null($column)) {
            $column = $this->getModel()->getCreatedAtColumn() ?? 'created_at';
        }

        return $this->orderBy($column, 'asc');
    }

    /**
     * Add a "group by" clause to the query.
     * Intelligently routes to schema or detail column.
     *
     * @param  array|string  ...$groups
     * @return $this
     */
    public function groupBy(...$groups)
    {
        // Handle multiple columns or array of columns
        $resolvedGroups = [];

        foreach ($groups as $group) {
            if (is_string($group)) {
                // Apply intelligent column resolution
                $resolvedGroups[] = $this->resolveColumn($group);
            } else {
                // Pass through non-string expressions (closures, Expressions, etc.)
                $resolvedGroups[] = $group;
            }
        }

        return parent::groupBy(...$resolvedGroups);
    }

    /**
     * Add a "having" clause to the query.
     * Intelligently routes to schema or detail column.
     *
     * @param  string  $column
     * @param  string|int|float|null  $operator
     * @param  string|int|float|null  $value
     * @param  string  $boolean
     * @return $this
     */
    public function having($column, $operator = null, $value = null, $boolean = 'and')
    {
        if (is_string($column)) {
            $column = $this->resolveColumn($column);
        }

        return parent::having($column, $operator, $value, $boolean);
    }

    /**
     * Add an "or having" clause to the query.
     *
     * @param  string  $column
     * @param  string|int|float|null  $operator
     * @param  string|int|float|null  $value
     * @return $this
     */
    public function orHaving($column, $operator = null, $value = null)
    {
        return $this->having($column, $operator, $value, 'or');
    }
}

