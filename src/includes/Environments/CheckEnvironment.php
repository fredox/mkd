<?php

class CheckEnvironment implements Environment
{
    public $name;
    public $strict;
    public static $debug = false;
    public $file = false;

    public function __construct($name, $strict=false, $file=false)
    {
        $this->name = $name;
        $this->strict = $strict;
        $this->file   = $file;
    }

    public function getName()
    {
        return $this->name;
    }

    public function get($queries, $key)
    {
        Output::print_msg("Environment of type check must be target", "ERROR", true);
    }

    public function put($data)
    {
        Output::print_msg("Initializing checks...", "CHECK][".$this->name);

        foreach ($data as $index => $rows) {
            if (empty($rows)) {
                $this->displayOk($index);
                continue;
            }
            foreach ($rows as $row) {
                if ($this->checkRowStructure($row)) {
                    $this->displayKo($index, $row);
                    if ($this->strict) {
                        Output::print_msg("This check is configured as strict and stops at the first fail", "CHECK", true);
                    }
                } else {
                    Output::print_msg("The result row does not have proper format.", "ERROR][CHECK", true);
                }
            }
        }

        if ($this->file) {
            Output::print_msg("Result check must be saved at: " . Input::INPUT_OUTPUT_FOLDER . '/' . $this->file['path'], "CHECK");
        }
    }

    private function displayOk($description)
    {
       Output::print_msg("[" . $description . "] ");
       Output::print_msg(greenFormat(" OK "));
    }

    private function displayKo($description, $row)
    {
        Output::print_msg("[" . $description . "]");
        Output::print_msg(" EXPECTED: " . whiteFormat($row['expected_value']));
        Output::print_msg(" ACTUAL: " . whiteFormat($row['real_value']) . " ");
        Output::print_msg(redFormat(" FAIL "));
        Output::print_msg("[" . $description . "] DESCRIPTION: " . $row['description']);
        Output::print_msg("- - - ");
        Output::intro();

        if (self::$debug) {
            $msg = $row['description'] . " -> " . $row['expected_value'] . ' - ' . $row['real_value'] . "\n";
            file_put_contents('result-check-debug.txt', $msg, FILE_APPEND);
        }

        if ($this->file) {
            if (!file_exists(Input::INPUT_OUTPUT_FOLDER . '/' . $this->file['path'])) {
                file_put_contents(Input::INPUT_OUTPUT_FOLDER . '/' . $this->file['path'], implode(',',$this->file['fields']));
            }

            $valuesToSave = array();

            foreach ($this->file['fields'] as $fieldToSave) {
                $valuesToSave[] = $row[$fieldToSave];
            }
            file_put_contents(Input::INPUT_OUTPUT_FOLDER . '/' . $this->file['path'], "\n" . implode(',', $valuesToSave), FILE_APPEND);
        }
    }

    private function checkRowStructure($row)
    {
        if (!is_array($row)) {
            return false;
        }

        $mandatoryFields = array('real_value', 'expected_value', 'description');

        foreach ($mandatoryFields as $field) {
            if (!in_array($field, array_keys($row))) {
                return false;
            }
        }

        return true;
    }

    public function describe($dataIndex)
    {
        return false;
    }

    public function getType()
    {
        return 'Check';
    }

}