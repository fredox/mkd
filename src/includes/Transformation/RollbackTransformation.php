<?php

include_once('Transformation.php');

class RollbackTransformation implements Transformation
{
    public function transform($data, Environment $sourceEnvironment, Environment $targetEnvironment)
    {
        Output::print_msg("[TRANSFORMATION][Rollback] Start", "INFO");

        if (!($sourceEnvironment instanceof MysqlEnvironment
            || $sourceEnvironment instanceof DryRunEnvironment
            || $sourceEnvironment instanceof SerializedDataFileEnvironment)
        ){
            exit("\n\n  [ERROR][ROLLBACK] Rollback transformation is only allowed with a Mysql, Dry run or serialized as a destination");
        }

        /* @var $sourceEnvironment MysqlEnvironment|DryRunEnvironment|SerializedDataFileEnvironment */

        $tablePrimaryKeys = array_reverse(MysqlEnvironment::$savedPrimaryKeys);

        foreach ($tablePrimaryKeys as $tableName => $primaryKeys) {
            Output::print_msg("[TRANSFORMATION][ROLLBACK] Collecting keys for table [" . $tableName . "]", "INFO");
            $primaryKeyField = $this->getPrimaryKeyOfTable($tableName, $targetEnvironment);
            if (!array_key_exists($tableName, $sourceEnvironment->rawQueries)) {
                $sourceEnvironment->rawQueries[$tableName] = array();
            }

            if ($primaryKeys[0] == 'FALSE') {
                continue;
            }

            if (!$this->isNumericArray($primaryKeys)) {
                $primaryKeys = $this->wrapStringArray($primaryKeys);
            }

            $sourceEnvironment->rawQueries[$tableName][] = "DELETE FROM " . $tableName . " WHERE " . $primaryKeyField . " IN (".implode(',', $primaryKeys) .")";
        }

        Output::print_msg("[TRANSFORMATION][Rollback] End", "INFO");

        return [];
    }

    private function getPrimaryKeyOfTable($table, Environment $targetEnvironment)
    {
        $fieldsDescription = $targetEnvironment->describe($table);

        foreach ($fieldsDescription as $fieldName => $fieldDescription) {
            if ($fieldDescription['key'] == 'PRI') {
                return $fieldName;
            }
        }

        return false;
    }

    private function isNumericArray($array)
    {
        foreach ($array as $element) {
            if (!is_numeric($element)) {
                return false;
            }
        }

        return true;
    }

    private function wrapStringArray($array)
    {
        foreach ($array as $key => $element) {
            $array[$key] = "'" . $element . "'";
        }

        return $array;
    }
}