<?php

class MongoDbEnvironment implements Environment, Queryable{
    public $name;
    public $host;
    public $port;
    public $dbname;
    public $user;
    public $password;
    public $connection;

    public function __construct($name, $host, $port, $dbname, $user, $password)
    {
        $this->name = $name;
        $this->host = $host;
        $this->port = $port;
        $this->dbname = $dbname;
        $this->user = $user;
        $this->password = $password;

        try {
            if (!in_array('mongodb', get_loaded_extensions())) {
                Output::intro();
                Output::print_msg(redFormat("Error, mongodb extension is not loaded"), "ERROR", true);
            }
            $this->connection =  new MongoDB\Driver\Manager("mongodb://".$this->user.":".$this->password."@".$this->host.":".$this->port."/?authSource=".$this->dbname);
        } catch (MongoConnectionException $e) {
            Output::print_msg("Error on Mongo connection: " . $e->getMessage(), "MONGO][ERROR", true);
        }
    }

    public function put($data)
    {
        $writeConcern = new MongoDB\Driver\WriteConcern(MongoDB\Driver\WriteConcern::MAJORITY, 100);
        if (empty($data)) {
            Output::print_msg("[" . $this->name . "] No regular data to execute", "MONGODB");
        } else {
            try {
                foreach ($data as $collectionName => $documents) {
                    if (empty($documents)) {
                        continue;
                    }
                    $bulk = new MongoDB\Driver\BulkWrite();
                    Output::print_msg("[" . $this->name . "][i] Exporting to collection ". whiteFormat("[" . $collectionName . "]"). " on [" . $this->name . "] environment", "INFO");
                    foreach ($documents as $document) {
                        $bulk->insert($document);
                    }
                    $result = $this->connection->executeBulkWrite($this->dbname.'.'.$collectionName, $bulk, $writeConcern);

                    Output::out_print(" [" . $result->getInsertedCount() . "] rows");
                }
            } Catch (Exception $e) {
                Output::print_msg("Error on put data: " . $e->getMessage(), "MONGO][ERROR", true);
            }
        }
    }

    public function get($queries, $key)
    {
        $finalResult = array();

        foreach ($queries as $collectionNameIndex => $filterData) {

            list($collectionName, $comment) = $this->getRealCollectionName($collectionNameIndex);

            $filterData['filter'] = json_decode(str_replace('@KEY', trim($key, "'"), json_encode($filterData['filter'])), true);

            Output::print_msg("collecting data from ". whiteFormat("[" . $collectionName . "]"), "INFO][" . $this->name);

            $result = $this->query($filterData, true);

            if (empty($result)) {
                Output::out_print(warningFormat(" no data "));
                if (empty($finalResult[$collectionName])) {
                    $finalResult[$collectionName] = false;
                }
            } else {
                Output::out_print( greenFormat("  ". count($result) ." docs "));


                if (array_key_exists($collectionName, $finalResult)) {
                    if ($finalResult[$collectionName] !== false) {
                        $finalResult[$collectionName] = array_merge($finalResult[$collectionName], $result);
                    } else {
                        $finalResult[$collectionName] = $result;
                    }
                } else {
                    $finalResult[$collectionName] = $result;
                }
            }
        }

        return $finalResult;
    }

    public function getType()
    {
        return 'mongodb';
    }

    public function query($query, $fetch = false)
    {
        $options = [];

        if (array_key_exists('projection', $query)) {
            $options['projection'] = $query['projection'];
        }

        $mongoQuery = new MongoDB\Driver\Query($query['filter'], $options);

        $result = [];

        $collection = $query['collection'];

        $cursor = $this->connection->executeQuery($this->dbname.".".$collection, $mongoQuery);

        foreach ($cursor as $doc) {
            $result[] = json_decode(json_encode($doc), true);
        }

        return $result;
    }

    public function getRealCollectionName($collectionName)
    {
        if (strpos($collectionName, ':') !== false) {
            list($collectionName, $comment) = explode(':', $collectionName);
            $comment = ' (' . $comment . ')';
        } else {
            $comment = '';
        }

        return array($collectionName, $comment);
    }

    public function getName()
    {
        return $this->name;
    }

    public function describe($dataIndex)
    {
        // TODO: Implement describe() method.
    }
}