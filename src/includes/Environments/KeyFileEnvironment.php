<?php

include_once('Environment.php');
include_once('includes/Input.php');

class KeyFileEnvironment implements Environment
{
    public $name;
    public $filePath;
    public $keyField;
    public static $firstExecutionByFile = array();
    public $fileAppend;
    public $defaultValue;

    public function __construct($name, $keyField='value', $fileAppend = false, $defaultValue=null)
    {
        $this->name     = $name;
        $this->filePath = Input::INPUT_OUTPUT_FOLDER . '/' . $name;
        $this->keyField = $keyField;
        $this->defaultValue = $defaultValue;
        $this->fileAppend = $fileAppend;
    }

    public function getName()
    {
        return $this->name;
    }

    public function get($queries, $key)
    {
        $data = file_get_contents($this->filePath);

        return $data;
    }

    public function put($data)
    {
        $keys = array();

        if (!array_key_exists($this->filePath, self::$firstExecutionByFile)) {
            Output::print_msg("Cleaning key file: " . $this->filePath, "KEY-FILE");
            file_put_contents($this->filePath, "");
            self::$firstExecutionByFile[$this->filePath] = true;
        }

        Output::print_msg("Saving keys into file: " . $this->filePath, "INFO");

        if (empty($data)) {
            Output::print_msg("Empty data set", "KEY-FILE][WARNING");

            if ($this->defaultValue !== null) {
                Output::print_msg("Applying default value: " . $this->defaultValue, "KEY-FILE");
                file_put_contents($this->filePath, $this->defaultValue);
                return;
            } else {
                Output::print_msg("No default value set for empty data sets", "KEY-FILE");
                return;
            }
        }

        foreach ($data as $index => $keyRows) {

            if (empty($keyRows)) {
                Output::print_msg("Empty data set [" . $index . "]", "KEY-FILE");
                $keys[] = $this->defaultValue;
                continue;
            }

            foreach ($keyRows as $row) {
                $value = (empty($row[$this->keyField])) ? $this->defaultValue : $row[$this->keyField];

                $keys[] = $value;
            }
        }

        array_unique($keys);

        if ($this->fileAppend) {
            Output::print_msg("Appending previous content to keys", "KEY-FILE][APPEND");
            $content = file_get_contents($this->filePath);
            if (!empty($content)) {
                $keys[] = $content;
            }
        }

        file_put_contents($this->filePath, implode(',', $keys));
    }

    public function describe($dataIndex)
    {
        return false;
    }

    public function getType()
    {
        return 'KeyFile';
    }
}