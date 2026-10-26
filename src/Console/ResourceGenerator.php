<?php

namespace Encore\Admin\Console;

use Illuminate\Database\Eloquent\Model;

class ResourceGenerator
{
    /**
     * @var Model
     */
    protected $model;

    /**
     * @var array
     */
    protected $formats = [
        'form_field'  => "\$form->%s('%s', __('%s'))",
        'show_field'  => "\$show->field('%s', __('%s'))",
        'grid_column' => "\$grid->column('%s', __('%s'))",
    ];

    /**
     * Generic column types, keyed by the native types reported by the database.
     *
     * @var array
     */
    private $columnTypeMapping = [
        'string'   => [
            'varchar', 'char', 'bpchar', 'character varying', 'character', 'uuid', 'citext', 'inet', 'cidr',
            'macaddr', 'enum', 'geometry', 'geometrycollection', 'linestring', 'polygon', 'multilinestring',
            'multipoint', 'multipolygon', 'point', 'nvarchar', 'nchar',
        ],
        'text'     => ['text', 'tinytext', 'mediumtext', 'longtext', 'ntext'],
        'blob'     => ['blob', 'tinyblob', 'mediumblob', 'longblob', 'bytea', 'binary', 'varbinary'],
        'boolean'  => ['bool', 'boolean', 'bit', 'tinyint'],
        'smallint' => ['smallint', 'int2', 'year'],
        'integer'  => ['int', 'integer', 'mediumint', 'int4', 'serial'],
        'bigint'   => ['bigint', 'int8', 'bigserial'],
        'decimal'  => ['decimal', 'numeric', 'money'],
        'float'    => ['float', 'double', 'double precision', 'real', 'float4', 'float8'],
        'datetime' => ['datetime', 'datetime2', 'timestamp', 'timestamptz', 'timestamp without time zone', 'timestamp with time zone'],
        'date'     => ['date'],
        'time'     => ['time', 'timetz', 'time without time zone', 'time with time zone'],
        'json'     => ['json', 'jsonb'],
    ];

    /**
     * @var array
     */
    protected $fieldTypeMapping = [
        'ip'       => 'ip',
        'email'    => 'email|mail',
        'password' => 'password|pwd',
        'url'      => 'url|link|src|href',
        'mobile'   => 'mobile|phone',
        'color'    => 'color|rgb',
        'image'    => 'image|img|avatar|pic|picture|cover',
        'file'     => 'file|attachment',
    ];

    /**
     * ResourceGenerator constructor.
     *
     * @param mixed $model
     */
    public function __construct($model)
    {
        $this->model = $this->getModel($model);
    }

    /**
     * @param mixed $model
     *
     * @return mixed
     */
    protected function getModel($model)
    {
        if ($model instanceof Model) {
            return $model;
        }

        if (!class_exists($model) || !is_string($model) || !is_subclass_of($model, Model::class)) {
            throw new \InvalidArgumentException("Invalid model [$model] !");
        }

        return new $model();
    }

    /**
     * @return string
     */
    public function generateForm()
    {
        $reservedColumns = $this->getReservedColumns();

        $output = '';

        foreach ($this->getTableColumns() as $column) {
            $name = $column['name'];
            if (in_array($name, $reservedColumns)) {
                continue;
            }
            $type = $column['type'];
            $default = $column['default'];

            $defaultValue = '';

            // set column fieldType and defaultValue
            switch ($type) {
                case 'boolean':
                case 'bool':
                    $fieldType = 'switch';
                    break;
                case 'json':
                case 'array':
                case 'object':
                    $fieldType = 'text';
                    break;
                case 'string':
                    $fieldType = 'text';
                    foreach ($this->fieldTypeMapping as $type => $regex) {
                        if (preg_match("/^($regex)$/i", $name) !== 0) {
                            $fieldType = $type;
                            break;
                        }
                    }
                    $defaultValue = var_export((string) $default, true);
                    break;
                case 'integer':
                case 'bigint':
                case 'smallint':
                case 'timestamp':
                    $fieldType = 'number';
                    break;
                case 'decimal':
                case 'float':
                case 'real':
                    $fieldType = 'decimal';
                    break;
                case 'datetime':
                    $fieldType = 'datetime';
                    $defaultValue = "date('Y-m-d H:i:s')";
                    break;
                case 'date':
                    $fieldType = 'date';
                    $defaultValue = "date('Y-m-d')";
                    break;
                case 'time':
                    $fieldType = 'time';
                    $defaultValue = "date('H:i:s')";
                    break;
                case 'text':
                case 'blob':
                    $fieldType = 'textarea';
                    break;
                default:
                    $fieldType = 'text';
                    $defaultValue = var_export((string) $default, true);
            }

            if (!$defaultValue && $default !== null) {
                $defaultValue = is_numeric($default) ? $default : var_export((string) $default, true);
            }

            $label = $this->formatLabel($name);

            $output .= sprintf($this->formats['form_field'], $fieldType, $name, $label);

            if (trim($defaultValue, "'\"")) {
                $output .= "->default({$defaultValue})";
            }

            $output .= ";\r\n";
        }

        return $output;
    }

    public function generateShow()
    {
        $output = '';

        foreach ($this->getTableColumns() as $column) {
            $name = $column['name'];

            // set column label
            $label = $this->formatLabel($name);

            $output .= sprintf($this->formats['show_field'], $name, $label);

            $output .= ";\r\n";
        }

        return $output;
    }

    public function generateGrid()
    {
        $output = '';

        foreach ($this->getTableColumns() as $column) {
            $name = $column['name'];
            $label = $this->formatLabel($name);

            $output .= sprintf($this->formats['grid_column'], $name, $label);
            $output .= ";\r\n";
        }

        return $output;
    }

    protected function getReservedColumns()
    {
        return [
            $this->model->getKeyName(),
            $this->model->getCreatedAtColumn(),
            $this->model->getUpdatedAtColumn(),
            'deleted_at',
        ];
    }

    /**
     * Get columns of a giving model.
     *
     * Each column is an array with the `name`, generic `type` and `default` value.
     *
     * @return array
     */
    protected function getTableColumns()
    {
        $columns = $this->model->getConnection()->getSchemaBuilder()->getColumns($this->model->getTable());

        return array_map(function ($column) {
            return [
                'name'    => $column['name'],
                'type'    => $this->getColumnType($column),
                'default' => $this->getColumnDefault($column),
            ];
        }, $columns);
    }

    /**
     * Resolve the generic type of a column reported by the schema builder.
     *
     * @param array $column
     *
     * @return string
     */
    protected function getColumnType(array $column)
    {
        $type = strtolower($column['type_name']);

        foreach ($this->columnTypeMapping as $genericType => $nativeTypes) {
            if (in_array($type, $nativeTypes, true)) {
                return $genericType;
            }
        }

        return 'string';
    }

    /**
     * Get the default value of a column without the quotes added by some drivers.
     *
     * @param array $column
     *
     * @return string|null
     */
    protected function getColumnDefault(array $column)
    {
        $default = $column['default'] ?? null;

        if (is_string($default) && strlen($default) > 1 && $default[0] === "'" && substr($default, -1) === "'") {
            return substr($default, 1, -1);
        }

        return $default;
    }

    /**
     * Format label.
     *
     * @param string $value
     *
     * @return string
     */
    protected function formatLabel($value)
    {
        return ucfirst(str_replace(['-', '_'], ' ', $value));
    }
}
