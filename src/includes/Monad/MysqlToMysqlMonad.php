<?php

include_once('MysqlToMysqlMonad.php');
include_once('SqlToSqlMonad.php');
include_once('Monad.php');

class MysqlToMysqlMonad extends SqlToSqlMonad implements Monad
{
    public $config;
    public $maxRowsPerInsert = 1;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @param $field
     * @param $row
     * @param $fieldsDefinition
     * @return string
     */
    public function getPreparedValue($field, $row, $fieldsDefinition)
    {
        $value = array_key_exists($field, $row) ? $row[$field] : null;

        if (($value === 0 OR $value == '0') AND self::isInt($fieldsDefinition[$field])) {
            return 0;
        }

        if (($value === 0 OR $value == '0') AND $fieldsDefinition[$field]['isText']) {
            return '"0"';
        }

        if (!empty($value)) {
           return $this->wrapNonEmptyValue($value, $fieldsDefinition[$field]);
        }

        if ($fieldsDefinition[$field]['null'] == 'YES') {
            return 'NULL';
        }

        if ($fieldsDefinition[$field]['default'] == "") {
            return '""';
        }

        $value = $fieldsDefinition[$field]['default'];

        return $this->wrapNonEmptyValue($value, $fieldsDefinition[$field]);
    }

    /**
     * @param $value
     * @return string
     */
    public function wrapNonEmptyValue($value, $fieldDefiniton)
    {
        if (self::isInt($fieldDefiniton)) {
            return $value;
        }

        if (is_numeric($value) && !$fieldDefiniton['isText']) {
            return $value;
        }

        return '"' . addslashes($value) . '"';
    }


    public function isInt($fieldDefinition)
    {
        return (($fieldDefinition['type'] == 'int') || ($fieldDefinition['type'] == 'tinyint'));
    }


    public function executeRawQueriesAtFirst($targetEnvironment)
    {
        $targetEnvironment->executeRawQueries();
    }
}