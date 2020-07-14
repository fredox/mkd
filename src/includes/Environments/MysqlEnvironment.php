<?php

include_once('Environment.php');
include_once('Queryable.php');

class MysqlEnvironment implements Environment, Queryable
{
    public $name;
    public $host;
    public $port;
    public $dbname;
    public $user;
    public $password;
    public $operation = 'INSERT';
    public $debug = false;
    public $socket = null;
    public $savePrimaryKeys = true;
    public $rawQueries = array();
    public static $cachedConnections = array();
    public static $savedPrimaryKeys = array();

    public $connection;

    public function __construct($name, $host, $port, $dbname, $user, $password, $savePrimaryKeys=true, $socket=null)
    {
        $this->name = $name;
        $this->host = $host;
        $this->port = $port;
        $this->dbname = $dbname;
        $this->user = $user;
        $this->password = $password;
        $this->socket = $socket;
        $this->savePrimaryKeys = $savePrimaryKeys;

        Output::print_msg("connecting to [" . $name . "]", "MYSQL ENVIRONMENT");

        $cacheHash = $this->getCacheHash();

        if (in_array($cacheHash, array_keys(static::$cachedConnections))) {
            Output::out_print("[CACHED]");
            $connection = static::$cachedConnections[$cacheHash];
        } else {
            $connection = new mysqli($host, $user, $password, $dbname, $port, $socket)
                or die("Could not connect to " . $this->name . " environment");

            if (!empty($connection->connect_error)) {
                Output::print_msg($connection->connect_error);

                Output::print_msg("[ERROR][MYSQL][" . $this->name . "] ". $connection->connect_error);
                Output::print_msg("[HOST] " . $host);
                Output::print_msg("[PORT] " . $port);
                Output::print_msg("[DB-NAME] " . $dbname);
                Output::print_msg("[DB-USER] " . $user);
                Output::print_msg("[DB-PASSWORD]" . $password);
                Output::intro(2, true);
            }
            static::$cachedConnections[$cacheHash] = $connection;
        }

        $this->connection = $connection;

        if ($this->savePrimaryKeys) {
            Output::print_msg("[IN MEMORY PRIMARY KEYS] " . implode(",", array_keys(static::$savedPrimaryKeys)));
        }
    }

    public function addRawQueries($descriptor, $queries)
    {
        $this->rawQueries[$descriptor] = $queries;
    }

    public function getCacheHash()
    {
        return md5($this->name . $this->host . $this->port . $this->dbname . $this->user);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getFields($table)
    {
        $fields = array();

        $resultSet = mysqli_query($this->connection, 'SHOW COLUMNS FROM ' . $table);

        while ($record = mysqli_fetch_assoc($resultSet)) {
            $fields[] = $record['Field'];
        }

        return $fields;
    }

    public function query($query, $fetch=false)
    {
        if ($this->debug == true) {
            file_put_contents('mysql.log', $query . "\n", FILE_APPEND);
        }

        if (empty($query)) {
            Output::print_msg("Empty query. Skipping data collection", "WARNING][MYSQL");
            return false;
        }

        $resultSet = mysqli_query($this->connection, $query);
        $result    = array();

        if ($resultSet === false) {
            if (preg_match("/^DESCRIBE (.*$)/", $query, $matches)) {
                Output::print_msg($matches[1] . " it is not a table ", "WARNING");
            } else {
                Output::print_msg(mysqli_error($this->connection), "ERROR");
            }

            return false;
        }

        if ($fetch) {
            while (($row = mysqli_fetch_assoc($resultSet)) !== null) {
                $result[] = $row;
            }
        }

        return $result;
    }

    public function affectedRows()
    {
        return mysqli_affected_rows($this->connection);
    }

    public function get($queries, $key)
    {
        $finalResult = array();

        foreach ($queries as $tableNameIndex => $query) {
            list($tableName, $comment) = $this->getRealTableName($tableNameIndex);

            if (empty($query)) {
                Output::print_msg("Empty query for [" . $tableNameIndex . "]", "WARNING");
                $finalResult[$tableName] = false;
                continue;
            }

            $query = str_replace('@KEY', $key, $query);

            if ($this->savePrimaryKeys) {
                $query = $this->replaceSavedPrimaryKeys($query);
            }

            Output::print_msg("collecting data from ". whiteFormat("[" . $tableName . $comment . "]"), "INFO][" . $this->name);
            $result = $this->query($query, true);

            if (empty($result)) {
                $this->savePrimaryKeys($tableName, $tableNameIndex, array(array('result' => 'FALSE')));
                Output::out_print(warningFormat(" no data "));
                if (empty($finalResult[$tableName])) {
                    $finalResult[$tableName] = false;
                }
            } else {
                Output::out_print( greenFormat("  ". count($result) ." rows "));

                if ($this->savePrimaryKeys) {
                    Output::print_msg("[MYSQL ENVIRONMENT][SAVING PRIMARY KEYS][" . $tableName . "]");
                    $this->savePrimaryKeys($tableName, $tableNameIndex, $result);
                }

                if (array_key_exists($tableName, $finalResult)) {
                    if ($finalResult[$tableName] !== false) {
                        $finalResult[$tableName] = array_merge($finalResult[$tableName], $result);
                    } else {
                        $finalResult[$tableName] = $result;
                    }
                } else {
                    $finalResult[$tableName] = $result;
                }
            }
        }

        return $finalResult;
    }

    public function replaceSavedPrimaryKeys($query)
    {
        foreach (static::$savedPrimaryKeys as $tableNameIndex => $rows) {
            if (is_numeric(static::$savedPrimaryKeys[$tableNameIndex][0])) {
                $replacement = implode(',', static::$savedPrimaryKeys[$tableNameIndex]);
            } else {
                $replacement = '"' . str_replace(',', '","', implode(',', static::$savedPrimaryKeys[$tableNameIndex])) . '"';
            }

            $query = str_replace('#' . $tableNameIndex, $replacement, $query);
        }

        return $query;
    }

    public function savePrimaryKeys($tableName, $tableNameIndex, $dataRows)
    {
        // If there is only one column the keys become this column.
        if (count(current($dataRows)) == 1) {
            $sample = array_keys(current($dataRows));
            $primaryKeyField = array_shift($sample);
            $keysToSave = array_column($dataRows, $primaryKeyField);
            static::$savedPrimaryKeys[$tableNameIndex] = $keysToSave;
            $this->saveMasterTableKeysIfNeeded($tableNameIndex, $keysToSave);
            return;
        }

        $fieldsDescription = $this->describe($tableName);
        $primaryKeyField   = false;

        foreach ($fieldsDescription as $fieldName => $fieldDescription) {
            if ($fieldDescription['key'] == 'PRI') {
                $primaryKeyField = $fieldName;
                break;
            }
        }

        if (!$primaryKeyField) {
            Output::print_msg("No primary key to save for table [" . $tableName . "]", "MYSQL-ENVIRONMENT");
            return;
        } else {
            Output::print_msg("Primary key [" . $primaryKeyField . "] for table [" . $tableName . "]", "MYSQL-ENVIRONMENT");
        }

        $keysToSave = array_column($dataRows, $primaryKeyField);
        $this->saveMasterTableKeysIfNeeded($tableNameIndex, $keysToSave);
        static::$savedPrimaryKeys[$tableNameIndex] = array_unique($keysToSave);
    }

    public function saveMasterTableKeysIfNeeded($tableNameIndex, $keys)
    {
        if (strpos($tableNameIndex, ':') !== false) {
            list($realTableNameIndex, $foo) = explode(':', $tableNameIndex);
            if (!array_key_exists($realTableNameIndex, static::$savedPrimaryKeys)) {
                static::$savedPrimaryKeys[$realTableNameIndex] = array();
            }

            $currentKeysContent = static::$savedPrimaryKeys[$realTableNameIndex];
            static::$savedPrimaryKeys[$realTableNameIndex] = array_unique(array_merge($currentKeysContent, $keys));
        }
    }

    public function getRealTableName($tableName)
    {
        if (strpos($tableName, ':') !== false) {
            list($tableName, $comment) = explode(':', $tableName);
            $comment = ' (' . $comment . ')';
        } else {
            $comment = '';
        }

        return array($tableName, $comment);
    }

    public function put($data)
    {
        if (empty($data)) {
            Output::print_msg("[" . $this->name . "] No regular data to execute", "MYSQL-ENVIRONMENT");
        } else {
            foreach ($data as $tableName => $queries) {
                $affectedRows = 0;



                if (empty($queries)) {
                    Output::print_msg("[" . $this->name . "] No data found for table [" . $tableName . "]");
                    Output::out_print(" [" . $affectedRows . "] rows");
                    continue;
                }

                Output::print_msg("[" . $this->name . "][i] Exporting to table ". whiteFormat("[" . $tableName . "]"). " on [" . $this->name . "] environment", "INFO");

                foreach ($queries as $query) {
                    $this->query($query);
                    $affectedRows += $this->affectedRows();
                }

                Output::out_print(" [" . $affectedRows . "] rows");
            }
        }
    }

    public function executeRawQueries()
    {
        if (!empty($this->rawQueries)) {
            Output::print_msg("[".$this->name."] Executing raw queries", "MYSQL-ENVIRONMENT");
            foreach ($this->rawQueries as $descriptor => $queries) {
                Output::print_msg("\n [MYSQL ENVIRONMENT][" . $this->name . "][RAW QUERIES][". $descriptor . "]");
                foreach ($queries as $query) {
                    $this->query($query);
                }
            }
        } else {
            Output::print_msg("No Raw queries to execute", "MYSQL-ENVIRONMENT");
        }

    }

    public function getType()
    {
        return 'Mysql';
    }

    public function describe($table)
    {
        $hashFields = array();
        $sql = 'DESCRIBE ' . $table;

        $fieldsInformation = $this->query($sql, true);

        if (empty($fieldsInformation)) {
            return $hashFields;
        }

        foreach ($fieldsInformation as $fieldInformation) {
            $fieldName = $fieldInformation['Field'];

            $hashFields[$fieldName]['type']    = $this->getDefinitionType($fieldInformation['Type']);
            $hashFields[$fieldName]['null']    = $fieldInformation['Null'];
            $hashFields[$fieldName]['key']     = $fieldInformation['Key'];
            $hashFields[$fieldName]['default'] = $fieldInformation['Default'];

            $hashFields[$fieldName]['isText'] = false;
            $textType = array('varchar', 'datetime', 'date', 'text', 'enum', 'set', 'char');

            foreach ($textType as $type) {
                if (strpos($hashFields[$fieldName]['type'], $type) === 0) {
                    $hashFields[$fieldName]['isText'] = true;
                    break;
                }
            }
        }

        return $hashFields;
    }

    private function getDefinitionType($type)
    {
        if (preg_match("/^([^\(]+)\(.*$/", $type, $matches)) {
            $type = $matches[1];
        }

        return strtolower($type);
    }
}












