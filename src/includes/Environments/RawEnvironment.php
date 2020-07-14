<?php

class RawEnvironment implements Environment
{
    public $name;
    public $rawQueries;
    public $putOperation;
    public $file = false;

    public function __construct($name, $putOperation, $file)
    {
        $this->name         = $name;
        $this->putOperation = $putOperation;
        $this->file         = $file;
    }

    public function getName()
    {
        return $this->name;
    }

    public function addRawQueries($descriptor, $queries)
    {
        if (!empty($queries)) {
            Output::print_msg("[RAW] setting " . count($queries) . " queries in Raw queries", "INFO");
            $this->rawQueries[$descriptor] = $queries;
        }
    }

    public function get($queries, $key)
    {
        $finalQueries = array();

        foreach ($queries as $index => $query) {
            $finalQueries[$index] = str_replace('@KEY', $key, $query);
        }

        //print_r($finalQueries);

        $this->addRawQueries('RAW', $finalQueries);

        return array();
    }

    public function put($data)
    {
        $op = $this->putOperation;
        $this->$op($data);
    }

    public function printRaw($data)
    {
        if ($this->file) {
            $content = print_r($data, TRUE);
            file_put_contents(Input::INPUT_OUTPUT_FOLDER . '/' . $this->file, $content);
        } else {
            $this->prettyPrintTables($data);
        }
    }

    public function prettyPrintTables($data) {
        foreach ($data as $table => $rows) {
            Output::print_msg(whiteFormat(" " . $table . " "));
            if (empty($rows)) {
                Output::print_msg("Data not found for table [" . $table . "]", "INFO");
                continue;
            }
            foreach ($rows as $row) {
                Output::print_msg("----------");
                foreach ($row as $field=>$value) {
                    Output::print_msg(blueFormat($field . ": ") . magentaFormat($value));
                }
            }
        }
    }

    private function saveJson($data)
    {
        $fileName = ($this->file) ? $this->file : false;
        JsonTableConverter::saveJson($data, $fileName);
    }

    public function describe($dataIndex)
    {
        return false;
    }

    public function getType()
    {
        return 'Raw';
    }

}
