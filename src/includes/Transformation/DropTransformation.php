<?php

include_once('Transformation.php');

class DropTransformation implements Transformation
{
    public $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function transform($data, Environment $sourceEnvironment, Environment $targetEnvironment)
    {
        Output::print_msg("[TRANSFORMATION][DROP] Collecting tables tables", "INFO");

        if (empty($tables)) {
            Output::print_msg("[TRANSFORMATION][DROP] There no tables to put schema", "INFO");
        }

        foreach ($data as $table=>$rows) {
            Output::print_msg("[TRANSFORMATION][DROP] Adding table [" . $table . "] to delete", "INFO");
            $rawData['Delete table ' . $table] = 'DROP TABLE IF EXISTS ' . $table;

        }

        $sourceEnvironment->addRawQueries('Drop tables', $rawData);

        // empty data because there is no info to insert in a table that does not exists
        $data = array();

        return $data;
    }
}