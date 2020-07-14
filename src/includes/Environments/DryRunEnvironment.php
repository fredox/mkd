<?php

class DryRunEnvironment implements Environment, Queryable
{
	public $name;
	public $filePath;
	public $fileAppend;
	public $rawQueries;
	public $output;

	const DRY_RUN_ENVIRONMENT_OUTPUT_FILE = 'file';
	const DRY_RUN_ENVIRONMENT_SCREEN = 'screen';

	public function __construct($name, $filePath, $fileAppend, $output)
	{
		$this->name       = $name;
		$this->filePath   = $filePath;
		$this->fileAppend = $fileAppend;
		$this->output     = $output;
	}

	public function getName()
    {
        return $this->name;
    }

    public function addRawQueries($descriptor, $queries)
    {
        if (!empty($queries)) {
            Output::print_msg(" Saving " . count($queries) . " queries in Raw queries", "DRY-RUN");
            $this->rawQueries[$descriptor] = $queries;
        } else {
            Output::print_msg("No Raw queries to save.", "DRY-RUN");
        }
    }

	public function get($queries, $key)
	{
	    Output::print_msg("Getting data from: " . $this->filePath, "DRY-RUN");

		$queries = array();

        $handle = fopen($this->filePath, "r");
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $queries[] = $line;
            }

            fclose($handle);
        } else {
            Output::print_msg("Can not open dry run file: " . $this->filePath, "DRY-RUN");
        }

        $this->addRawQueries('DRY-RUN-FILE-QUERIES: ' . $this->filePath . '', $queries);

        return array();
	}

	public function put($data)
	{
	    if (!$this->fileAppend) {
	        Output::print_msg("Cleaning dry run file: " . $this->filePath, "DRY-RUN");
            $this->saveInFile("", false);
        }

	    Output::print_msg("Saving data transfer into file: " . $this->filePath, "DRY-RUN");

        if (!empty($this->rawQueries)) {
            foreach ($this->rawQueries as $descriptor => $rQueries) {
                Output::intro();
                Output::print_msg($descriptor);
                Output::intro();
                $this->saveInFile("\n" . '--' . $descriptor);
                foreach ($rQueries as $rQuery) {
                    Output::print_msg("Saving eaw queries in " . $this->filePath, "DRY-RUN][RAW QUERIES");
                    $this->saveInFile("\n" . $rQuery . ";");
                }
            }
        } else {
            Output::print_msg("Empty raw data queries", "DRY-RUN");
        }

        if (empty($data)) {
            Output::print_msg("Empty regular data set", "DRY-RUN][WARNING");
            $this->saveInFile("\n -- No Regular data found");
            return;
        }

        foreach ($data as $index => $queries) {
            $msgTable =  "\n -- [" . $index . "] to environment [" . $this->name . "]";
            Output::print_msg($msgTable);
            $this->saveInFile("\n" . $msgTable);

            if (empty($queries)) {
                Output::print_msg("Empty data set [" . $index . "]", "DRY-RUN][WARNING");
                continue;
            }

            foreach ($queries as $query) {
                $this->saveInFile("\n" . $query . ";");
            }

        }
	}

	public function saveInFile($data, $fileAppend=true)
    {
        if ($this->output == self::DRY_RUN_ENVIRONMENT_OUTPUT_FILE) {
            $fileAppend = ($fileAppend) ? FILE_APPEND : 0;
            file_put_contents($this->filePath, $data, $fileAppend);
        } else {
            Output::print_msg($data);
        }

    }

	public function describe($dataIndex)
    {
        return false;
    }

	public function getType()
    {
        return 'DryRun';
    }


    public function query($query, $fetch = false)
    {
        return [];
    }

}