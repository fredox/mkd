<?php

include_once('Monad.php');
include_once('PgsqlToPgsqlMonad.php');
include_once('SqlToSqlMonad.php');

class PgsqlToPgsqlMonad extends SqlToSqlMonad implements Monad
{
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
            return "'0'";
        }

        if (!empty($value)) {
            return $this->wrapNonEmptyValue($value, $fieldsDefinition[$field]);
        }

        if ($fieldsDefinition[$field]['null'] == 'YES') {
            return 'NULL';
        }

        if ($fieldsDefinition[$field]['default'] == "") {
            if (self::isInt($fieldsDefinition[$field])) {
                return 0;
            } else {
                return "''";
            }
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

        return "'" . str_replace("'", "''",($value)) . "'";
    }

    public function isInt($fieldDefinition)
    {
        $intTypes = ['int2', 'int4', 'int8', 'bigint'];
        return in_array($fieldDefinition['type'], $intTypes);
    }
}