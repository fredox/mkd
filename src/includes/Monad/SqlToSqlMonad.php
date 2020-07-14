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

            foreach ($rows as $index => $row) {

                foreach ($fieldsDefinition as $fieldName => $fieldDefinition) {
                    $finalData[$tableName][$index][$fieldName] = $this->getPreparedValue($fieldName, $row, $fieldsDefinition);
                }
            }

        }

        foreach ($finalData as $tableName => $rows) {

            $fields = array_keys($rows[0]);

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