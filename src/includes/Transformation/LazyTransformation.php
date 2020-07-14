<?php

include_once('Transformation.php');

class LazyTransformation implements Transformation
{
    public function transform($data, Environment $sourceEnvironment, Environment $targetEnvironment)
    {
        Output::print_msg("[TRANSFORMATION][LAZY] Skipping Duplicates", "INFO");

        $finalData = array();
        $skipped = 0;

        foreach ($data as $table=>$rows) {

            $primaryKeyField = $this->getPrimaryKeyOfTable($table, $targetEnvironment);

            if (!$primaryKeyField) {
                $finalData[$table] = $rows;
                continue;
            }

            $primaryKeys = $this->getPrimaryKeysOfData($rows, $primaryKeyField);
            $notDuplicated = $this->getNotDuplicatedKeys($table, $primaryKeyField, $primaryKeys, $targetEnvironment);

            foreach ($rows as $row) {
                if (in_array($row[$primaryKeyField], $notDuplicated)) {
                    $finalData[$table][] = $row;
                } else {
                    $skipped++;
                }
            }

            if ($skipped != 0) {
                Output::print_msg("[TRANSFORMATION][LAZY] Skipped [" . $skipped . "] rows cause duplication in table [". $table . "]", "INFO");
            }

            $skipped = 0;

        }

        return $finalData;
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

    private function getPrimaryKeysOfData($rows, $primaryKeyField)
    {
        $primaryKeys = array();

        foreach ($rows as $row) {
            $primaryKeys[] = $row[$primaryKeyField];
        }

        return $primaryKeys;
    }

    private function getNotDuplicatedKeys($table, $primaryKeyField, $primaryKeys, Environment $targetEnvironment)
    {
        $existingKeys = array();

        $query  = 'SELECT ' . $primaryKeyField . ' FROM ' . $table . ' WHERE ' . $primaryKeyField . ' IN ';
        $query .= ' (' . implode(',', $primaryKeys) . ')';

        $rows = $targetEnvironment->query($query, true);

        foreach ($rows as $row) {
            $existingKeys[] = $row[$primaryKeyField];
        }

        return array_diff($primaryKeys, $existingKeys);
    }
}