<?php

Class Health {

    public static $configDir;
    public static $queries;
    public static $config;

    public static function checkHealth($healthParam, $params)
    {
        if ($healthParam != '-health') {
            return;
        }

        self::checkConfigFolder($params);
        self::loadQueries();
        self::loadConfig();
        self::checkAllGroupsContainsValidQueries();

        self::endCheck();
    }

    public static function endCheck()
    {
        Output::print_msg("Check end", "HEALTH", true);
    }

    public static function checkConfigFolder($params)
    {
        if (!array_key_exists(1, $params)) {
            Output::print_msg("Health param must be preceded by a config folder\n\n", "ERROR][HEALTH");
            InputHelp::showConfigFolders();
            exit;
        }

        if (is_dir('config/' . $params[1])) {
            self::$configDir = 'config/' . $params[1];
            Output::print_msg("Directory " . $params[1] . " exists", "OK][HEALTH");
        } else {
            Output::print_msg("Directory " . $params[1] . " does NOT exists", "OK][HEALTH", true);
        }
    }

    public static function loadQueries()
    {
        if (!is_file(self::$configDir . "/queries.php")) {
            Output::print_msg("queries file does not exists in " . self::$configDir . "\n\n", true);
        }

        Output::print_msg("Queries file exists", "OK][HEALTH");

        $queries = array();

        include_once(self::$configDir . "/queries.php");

        self::$queries = $queries;
    }

    public static function loadConfig()
    {
        if (!is_file(self::$configDir . "/config.php")) {
            Output::print_msg("config file does not exists in " . self::$configDir . "\n\n", "ERROR][HEALTH", true);
        }

        Output::print_msg("Config file exists", "OK][HEALTH");

        $config = array();

        include_once(self::$configDir . "/config.php");

        self::$config = $config;
    }

    public static function checkAllGroupsContainsValidQueries()
    {
        foreach (self::$config['groups'] as $groupName => $queryIndexes) {
            Output::print_msg("Checking group [" . $groupName . "]", "INFO][HEALTH");

            foreach ($queryIndexes as $queryIndex) {
                if (!in_array($queryIndex, array_keys(self::$queries))) {
                    Output::print_msg("query index [" . $queryIndex . "] is not present in queries file.", "ERROR][HEALTH", true);
                }
            }
        }
    }
}