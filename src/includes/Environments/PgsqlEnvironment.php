<?php

include_once('Environment.php');
include_once('Queryable.php');

class PgsqlEnvironment implements Environment, Queryable
{
    public $name;
    public $host;
    public $port;
    public $dbname;
    public $user;
    public $password;
    public $operation = 'INSERT';
    public $savePrimaryKeys = true;
    public $rawQueries = array();
    public $debug = false;
    public static $cachedConnections = array();
    public static $savedPrimaryKeys = array();
    public static $primaryKeyFieldsByTable = array();
    public static $affectedRows = 0;
    public static $optimized = true;

    public $connection;

    public function __construct($name, $host, $port, $dbname, $user, $password, $savePrimaryKeys=true)
    {
        $this->name = $name;
        $this->host = $host;
        $this->port = $port;
        $this->dbname = $dbname;
        $this->user = $user;
        $this->password = $password;
        $this->savePrimaryKeys = $savePrimaryKeys;

        Output::print_msg("Connecting to [" . $name . "]", "PGSQL-ENVIRONMENT");

        $cacheHash = $this->getCacheHashForPostgres();

        if (in_array($cacheHash, array_keys(static::$cachedConnections))) {
            Output::out_print("[CACHED]");
            $connection = static::$cachedConnections[$cacheHash];
        } else {
            $connection = pg_connect("host=" . $host . " port=" . $port . " dbname=" . $dbname . " user=" . $user . " password=" . $password);

            if (!$connection) {
                Output::out_print("[ERROR] Couldn't make connection to " . $this->name . " environment");
                Output::out_print("[ERROR][PGSQL][" . $this->name . "] ". pg_last_error($connection) . "\n\n");
                Output::out_print("[HOST] " . $host);
                Output::out_print("[PORT] " . $port);
                Output::out_print("[DB-NAME] " . $dbname);
                Output::out_print("[DB-USER] " . $user);
                Output::out_print("[DB-PASSWORD]" . $password);
                Output::intro(2,true);
            }
            static::$cachedConnections[$cacheHash] = $connection;
        }

        $this->connection = $connection;
    }

    public function getCacheHashForPostgres()
    {
        return md5($this->name . $this->host . $this->port . $this->dbname . $this->user);
    }


    public function put($data)
    {
        if (empty($data)) {
            Output::print_msg("[" . $this->name . "] No regular data to execute", "PGSQL-ENVIRONMENT");
        } else {
            if (self::$optimized) {
                $this->optimizedImport($data);
            } else {
                $this->regularImport($data);
            }
        }
    }

    public function regularImport($data)
    {
        foreach ($data as $tableName => $queries) {
            $affectedRows = 0;

            if (empty($queries)) {
                Output::print_msg("\n [" . $this->name . "] No data found for table [" . $tableName . "]", "WARNING");
            }

            Output::print_msg("\n   [" . $this->name . "] Exporting to table " . whiteFormat("[" . $tableName . "]") . " on [" . $this->name . "] environment", "INFO");


            foreach ($queries as $query) {
                $this->query($query);
                $affectedRows += $this->affectedRows();
            }

            Output::out_print(" [" . $affectedRows . "] rows");
        }
    }

    public function optimizedImport($data)
    {
        $finalQuery = '';

        Output::print_msg("[PGSQL] Optimized import for tables: " . implode(", ", array_keys($data)), "INFO");
        foreach ($data as $table => $queries) {
            if (empty($queries)) {
                Output::print_msg("[PGSQL] No rows to import on table: " . $table, "INFO");
                continue;
            }

            foreach ($queries as $query) {
                $finalQuery .= $query . "; ";
            }
        }
        $this->query($finalQuery);
        Output::print_msg("[PGSQL] Optimized import end.", "INFO");
    }

    public function affectedRows()
    {
        return self::$affectedRows;
    }

    public function get($queries, $key)
    {
        $finalResult = array();

        foreach ($queries as $tableNameIndex => $query) {
            list($tableName, $comment) = $this->getRealTableName($tableNameIndex);

            if (empty($query)) {
                Output::print_msg("[". $this->name ."] Empty query for [" . $tableNameIndex . "]", "WARNING");
                $finalResult[$tableName] = false;
                continue;
            }

            $query = str_replace('@KEY', $key, $query);

            if ($this->savePrimaryKeys) {
                $query = $this->replaceSavedPrimaryKeys($query);
            }

            Output::print_msg("[". $this->name ."] collecting data from ". whiteFormat("[" . $tableName . $comment . "]"), "INFO");

            if (!$this->tableExists($tableName)) {
                Output::out_print(warningFormat(" no table found "));
                continue;
            }

            $result = $this->query($query, true);

            if (empty($result)) {
                $pkInfo = $this->describe($tableName, true);

                if ($pkInfo !== false) {
                    if ($pkInfo['isText']) {
                        $emptyResult = array(array('result' => ''));
                    } else {
                        $emptyResult = array(array('result' => 0));
                    }

                    $this->savePrimaryKeys($tableName, $tableNameIndex, $emptyResult);
                }
                Output::out_print(warningFormat(" no data "));
                if (empty($finalResult[$tableName])) {
                    $finalResult[$tableName] = false;
                }
            } else {
                Output::out_print( greenFormat(" ". count($result) ." rows "));

                if ($this->savePrimaryKeys) {
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

    public function tableExists($tableName)
    {
        $table = $this->query("SELECT * FROM information_schema.tables WHERE table_name = '" . $tableName . "'", true);

        return (count($table) >= 1);
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

        if ($primaryKeyField === false) {
            static::$savedPrimaryKeys[$tableNameIndex] = [];
            return;
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

    public function replaceSavedPrimaryKeys($query)
    {
        foreach (static::$savedPrimaryKeys as $tableNameIndex => $rows) {
            if ($this->getPrimaryKeyField($tableNameIndex) === false) {
                continue;
            }
            if (array_key_exists(0, static::$savedPrimaryKeys[$tableNameIndex]) && is_numeric(static::$savedPrimaryKeys[$tableNameIndex][0]) && ($this->getPrimaryKeyField($tableNameIndex)['udt_name'] != 'varchar')) {
                $replacement = implode(',', static::$savedPrimaryKeys[$tableNameIndex]);
            } else {
                $replacement = "'" . implode("','", static::$savedPrimaryKeys[$tableNameIndex]) . "'";
            }

            $query = str_replace('#' . $tableNameIndex, $replacement, $query);
        }

        return $query;
    }

    public function query($query, $fetch=false)
    {
        if ($this->debug == true) {
            file_put_contents('pgsql.log', $query . "\n", FILE_APPEND);
        }

        if (empty($query)) {
            Output::print_msg("[PGSQL] Empty query. Skipping data collection", "WARNING");
            return false;
        }

        $resultSet = pg_query($this->connection, $query);
        $result = array();

        if ($resultSet === false) {

            Output::print_msg("[PGSQL] " . pg_last_error($this->connection), "ERROR");
            self::$affectedRows = 0;
            return false;
        }
        if ($fetch) {
            while (($row = pg_fetch_assoc($resultSet))) {
                $result[] = $row;
            }
        }
        self::$affectedRows = pg_affected_rows($resultSet);
        return $result;
    }

    public function getType()
    {
        return 'Pgsql';
    }

    public function getName()
    {
        return $this->name;
    }

    public function describe($table, $pk=false)
    {
        $hashFields = array();
        $sql = "select * from information_schema.columns where table_name ='" . $table ."'";

        $fieldsInformation = $this->query($sql, true);

        if (empty($fieldsInformation)) {
            return $hashFields;
        }

        foreach ($fieldsInformation as $fieldInformation) {
            $fieldName = $fieldInformation['column_name'];

            $hashFields[$fieldName]['type']    = $fieldInformation['udt_name'];
            $hashFields[$fieldName]['null']    = $fieldInformation['is_nullable'];
            if ($this->getPrimaryKeyField($table) === false) {
                $hashFields[$fieldName]['key'] = "";
            } else {
                $hashFields[$fieldName]['key'] = ($fieldName == $this->getPrimaryKeyField($table)['column_name']) ? "PRI" : "";
            }
            $hashFields[$fieldName]['default'] = $fieldInformation['column_default'];

            $hashFields[$fieldName]['isText'] = false;
            $textType = array('varchar', 'datetime', 'date', 'text', 'enum', 'set', 'char');

            $hashFields[$fieldName]['isText'] = false;
            if (in_array($hashFields[$fieldName]['type'], $textType)) {
                $hashFields[$fieldName]['isText'] = true;
            }

            if (($hashFields[$fieldName]['key'] == 'PRI') && $pk){
                return $hashFields[$fieldName];
            }
        }

        if ($pk) return false;

        return $hashFields;
    }

    public function getPrimaryKeyField($tableName)
    {
        if (!key_exists($tableName, self::$primaryKeyFieldsByTable)) {
            $sql = "SELECT c.column_name, c.udt_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.constraint_column_usage AS ccu USING (constraint_schema, constraint_name)
            JOIN information_schema.columns AS c ON c.table_schema = tc.constraint_schema
              AND tc.table_name = c.table_name AND ccu.column_name = c.column_name
            WHERE constraint_type = 'PRIMARY KEY' and tc.table_name = '" . $tableName . "';";

            $result = $this->query($sql, true);

            if (!$result) {
                return false;
            }

            self::$primaryKeyFieldsByTable[$tableName] = $result[0];
        }

        return self::$primaryKeyFieldsByTable[$tableName];
    }

    public function executeRawQueries()
    {
        if (!empty($this->rawQueries)) {

            Output::print_msg("[PGSQL ENVIRONMENT][" . $this->name . "] Executing raw queries", "INFO");
            foreach ($this->rawQueries as $descriptor => $queries) {
                Output::print_msg("[PGSQL ENVIRONMENT][" . $this->name . "][RAW QUERIES][". $descriptor . "]", "INFO");
                foreach ($queries as $query) {
                    $this->query($query);
                }
            }
        } else {
            Output::print_msg("[PGSQL ENVIRONMENT] No Raw queries to execute", "INFO");
        }

    }
}