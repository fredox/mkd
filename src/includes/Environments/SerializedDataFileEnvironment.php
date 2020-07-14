<?php

class SerializedDataFileEnvironment implements Environment, Queryable
{
    public $name;
    public $filePath;
    public $configTag;
    public $rawQueries = array('RAW'=> array('raw-queries-to-execute' => array()));
    public $selectedQueriesKeys = array();

    public function __construct($name, $filePath, $configTag = 'none')
    {
        $this->name       = $name;
        $this->filePath   = $filePath;
        $this->configTag  = $configTag;
    }

    public function getName()
    {
        return $this->name;
    }

    public function get($queries, $key)
    {
        $returnedData = array();

        Output::print_msg("[SerializedDataFile] reading data from file [" . $this->filePath . "]", "INFO");
        $this->checkFile();


        $data = $this->getUnserializedContent();

        $selectedKeys = array_keys($queries);

        $this->selectedQueriesKeys = $this->removeRawKeys($selectedKeys, $data, $this->configTag);

        if (!array_key_exists($this->configTag, $data)) {
            Output::print_msg("[SerializedDataFile] No data for config: [" . $this->configTag . "]", "INFO");
            return $returnedData;
        }

        foreach ($this->selectedQueriesKeys as $queryKey) {

            if (in_array($queryKey, array_keys($data[$this->configTag]))) {
                Output::print_msg("[SerializedDataFile] Getting data from [" . $queryKey . "]", "INFO");
                $returnedData[$queryKey] = $data[$this->configTag][$queryKey];
                $nRows = empty($returnedData[$queryKey]) ? 0 : count($returnedData[$queryKey]);
                Output::out_print(" (" . $nRows . ")");
            } else {
                Output::print_msg("[SerialiedDataFile] index [" . $queryKey . "] not present in serialized file: " . $this->filePath, "WARNING");
            }
        }

        return $returnedData;
    }

    public function put($data)
    {
        Output::print_msg("[SerializedDataFile] putting data to [" . $this->filePath . "] file", "INFO");

        $currentContent = $this->getUnserializedContent();

        if (!is_array($currentContent)) {
            $currentContent = array();
        }

        if (!key_exists($this->configTag, $currentContent)) {
            $currentContent[$this->configTag] = array();
        }

        foreach ($data as $queryIndex => $rows) {
            $currentContent[$this->configTag][$queryIndex] = $rows;
        }

        $this->putSerializedContent($currentContent);
    }

    public function putSerializedContent($unSerializedContent)
    {
        $serializedData = serialize($unSerializedContent);
        file_put_contents($this->filePath, $serializedData);
    }

    public function getUnserializedContent()
    {
        if (!is_file($this->filePath)) {
            Output::print_msg("[SerializedDataFile] Creating file for serialize data: " . $this->filePath, "INFO");
            file_put_contents($this->filePath,'');
        }

        $serializedData = file_get_contents($this->filePath);
        $data           = unserialize($serializedData);

        if (empty($data)) {
            $data = array();
        }

        if (!key_exists('RAW', $data)) {
            $data['RAW'] = array();
        }

        if (!key_exists($this->configTag, $data['RAW'])) {
            $data['RAW'][$this->configTag] = array();
        }

        return $data;
    }

    private function checkFile()
    {
        if (!is_file($this->filePath)) {
            Output::print_msg("[SerializedDataFile] Creating file: " . $this->filePath, "INFO");
            file_put_contents($this->filePath, '');
        }
    }

    public function saveRawQueries()
    {
        $currentContent = $this->getUnserializedContent();

        if (!empty($this->rawQueries)) {
            foreach ($this->rawQueries['RAW'] as $queryIndex => $query) {
                $currentContent['RAW'][$this->configTag][$queryIndex] = $query;
            }
        }

        $this->putSerializedContent($currentContent);
    }

    public function getRawQueries()
    {
        $content = $this->getUnserializedContent();
        $result  = array();

        if (!empty($content) && is_array($content) && array_key_exists('RAW', $content)) {
            foreach ($content['RAW'][$this->configTag] as $queryIndex => $query) {
                if (empty($this->selectedQueriesKeys)) {
                    return array('RAW' => $content['RAW'][$this->configTag]);
                }

                if (in_array($queryIndex, $this->selectedQueriesKeys)) {
                    $result['RAW'][$queryIndex] = $query;
                }
            }
        }

        return $result;
    }

    public function removeRawKeys($selectedKeys, $data, $configTag)
    {
        $regularKeys    = array();
        $rawConfigKeys  = array_keys($data['RAW'][$configTag]);

        foreach ($selectedKeys as $selectedKey) {
            if (!in_array($selectedKey, $rawConfigKeys)) {
                $regularKeys[] = $selectedKey;
            }
        }

        // Remove comments from tables
        if (!empty($regularKeys)) {
            foreach ($regularKeys as $index => $regularKey) {
                if (strpos($regularKey, ':') !== false) {
                    $regularKeys[$index] = explode(':', $regularKey)[0];
                }
            }
        }

        return array_unique($regularKeys);
    }

    public function describe($dataIndex)
    {
        return false;
    }

    public function getType()
    {
        return 'SerializedDataFile';
    }

    public function query($query, $fetch = false)
    {
        return [];
    }
}