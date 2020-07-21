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

    public function prettyPrintTables($data, $prefix="") {
        foreach ($data as $table => $rows) {
            Output::print_msg(whiteFormat(" " . $table . " "));
            if (empty($rows)) {
                Output::print_msg("Data not found for table [" . $table . "]", "INFO");
                continue;
            }
            foreach ($rows as $row) {
                Output::print_msg("----------");
                $this->printFields($row);
            }
        }
    }

    public function printFields($row, $prefix="") {
        foreach ($row as $field=>$value) {
            if (is_array($value)) {
                Output::print_msg($prefix . blueFormat($field . ": "));
                $this->printFields($value, "  " . $prefix);
            } else {
                Output::print_msg($prefix . blueFormat($field . ": ") . magentaFormat($value));
            }
        }
    }

    private function saveJsonFromTabData($data)
    {
        $fileName = ($this->file) ? $this->file : false;
        JsonTableConverter::saveJsonFromTabData($data, $fileName);
    }

    private function saveJson($data)
    {
        foreach ($data as $dataIndex => $rows) {
            file_put_contents('json-' . $dataIndex . '.json', json_encode($rows));
        }
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
