<?php

include_once('Transformation.php');

class SchemaTransformation implements Transformation
{
    public $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function transform($data, Environment $sourceEnvironment, Environment $targetEnvironment)
    {
        Output::print_msg("[TRANSFORMATION][SCHEMA] Adding create tables", "INFO");

        $tablesInTargetEnvironment = $rawData = array();

        $tables = $targetEnvironment->query('SHOW TABLES', true);

        if (empty($tables)) {
            Output::print_msg("[TRANSFORMATION][SCHEMA] There no tables in target environment", "INFO");
        }

        foreach ($tables as $tableInTargetEnvironment) {
            $tablesInTargetEnvironment[] = $tableInTargetEnvironment['Tables_in_' . $targetEnvironment->dbname];
        }

        foreach ($data as $table=>$rows) {
            $targetEnvironmentType = $this->getTargetEnvironmentRealType();

            if (!in_array($table, $tablesInTargetEnvironment) || ($targetEnvironmentType == 'dryrun')) {
                Output::print_msg("[TRANSFORMATION][SCHEMA] Adding create table for [" . $table . "]", "INFO");
                $query = 'SHOW CREATE TABLE ' . $table;
                $createTable = $sourceEnvironment->query($query, true);

                if ($createTable === false) {
                    Output::print_msg("[TRANSFORMATION][SCHEMA] Source environment table: " . $table . " does not exists", "INFO");
                    continue;
                }

                $createTableStatement = $createTable[0]['Create Table'] . ";";
                $createTableStatement = str_replace(array("\n","\r"), " ", $createTableStatement);


                $rawData['Create table ' . $table] = $createTableStatement;
            }
        }

        $sourceEnvironment->addRawQueries('Creation tables', $rawData);

        return $data;
    }

    public function getTargetEnvironmentRealType()
    {
        $targetEnvirnmentName = $this->config['execution']['target_environment'];

        return $this->config['environments'][$targetEnvirnmentName]['type'];
    }
}