<?php

class SqlToSqlMonad {
    public $config;
    public $maxRowsPerInsert = 1;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param $data
     * @param Environment $targetEnvironment
     * @return array
     */
    public function unit($data, Environment $targetEnvironment)
    {
        $finalData = array();
        $sqlData   = array();

        $this->executeRawQueriesAtFirst($targetEnvironment);

        foreach ($data as $tableName => $rows) {

            if (empty($rows)) {
                continue;
            }

            $fieldsDefinition = $targetEnvironment->describe($tableName);

            if ($fieldsDefinition === false) {
                Output::print_msg("Environment [" . $targetEnvironment->getName() . "] don't implements describe function", "ERROR");
                exit;
            }

            $primaryField = $this->getPrimaryKeyField($fieldsDefinition);
            $existingKeys = $this->existingKeys($rows, $tableName, $primaryField, $targetEnvironment);
            $tableExistingKeys = 0;

            foreach ($rows as $index => $row) {
                if ($primaryField !== false && in_array($row[$primaryField['name']], $existingKeys)) {
                    $tableExistingKeys++;
                    continue;
                }
                foreach ($fieldsDefinition as $fieldName => $fieldDefinition) {
                    $finalData[$tableName][$index][$fieldName] = $this->getPreparedValue($fieldName, $row, $fieldsDefinition);
                }
            }
            if ($tableExistingKeys > 0) {
                Output::print_msg("Table [" . $tableName . "] already have " . $tableExistingKeys . " rows.", "INFO");
            }
        }

        foreach ($finalData as $tableName => $rows) {

            $fields = array_keys(reset($rows));

            $insertedRows = 0;
            $nRows  = count($rows);
            $values = array();

            foreach ($rows as $index => $row) {
                $values[] = "(" . implode($row, ',') . ")";
                $insertedRows++;

                $lastRowInserted = ($nRows == $insertedRows);
                $nextInsertBulk  = ($insertedRows % $this->maxRowsPerInsert) == 0;

                if ($lastRowInserted || $nextInsertBulk) {
                    $query  = $targetEnvironment->operation;
                    $query .= " INTO " . $tableName . " (" . implode($fields, ',') . ") VALUES ";
                    $query .= implode(',', $values);

                    $values = array();
                    $sqlData[$tableName][] = $query;
                }
            }

        }

        return $sqlData;
    }

    public function existingKeys($rows, $table, $primaryField, $targetEnvironment)
    {
        $existingKeys = [];

        if (!$targetEnvironment instanceof Queryable) {
            return $existingKeys;
        }

        if ($primaryField !== false) {
            $rowKeys = [];
            foreach ($rows as $row) {
                $rowKeys[] = $primaryField['isText'] ? "'" . $row[$primaryField['name']] . "'" : $row[$primaryField['name']];
            }
            $query = "SELECT " . $primaryField['name'] . " as k FROM " . $table . " WHERE " . $primaryField['name'] . " IN (" . implode(",", $rowKeys) . ");";
            $existingKeys = $targetEnvironment->query($query, true);
            $existingKeys = array_map(function($row) { return $row['k']; }, $existingKeys);
        }

        return $existingKeys;
    }

    public function getPrimaryKeyField($fieldsDefinition)
    {
        foreach ($fieldsDefinition as $fieldName => $field) {
            if ($field['key'] == "PRI") {
                return ['name' => $fieldName, 'isText' => $field['isText']];
            }
        }

        return false;
    }


    /**
     * @param $data
     * @param Environment $sourceEnvironment
     * @param Environment $targetEnvironment
     * @param array $transformations
     * @return array
     */
    public function bind($data, Environment $sourceEnvironment, Environment $targetEnvironment, $transformations = array())
    {
        if (!empty($transformations)) {
            foreach ($transformations as $transformation) {
                Output::print_msg("[TRANSFORMATIONS] Applying Transformation " . get_class($transformation), "INFO");
                $data = $transformation->transform($data, $sourceEnvironment, $targetEnvironment);
            }
        }

        $targetEnvironment->rawQueries = $sourceEnvironment->rawQueries;

        return $this->unit($data, $targetEnvironment);
    }

    public function executeRawQueriesAtFirst($targetEnvironment)
    {
        $targetEnvironment->executeRawQueries();
    }
}