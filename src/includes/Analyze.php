<?php

include_once('Environments/EnvironmentFactory.php');

class Analyze extends Health {

    /** @var MysqlEnvironment */
    public static $environment;

    /** @var array */
    public static $tablesInformation;

    /** @var array */
    public static $topologicalOrderTables;

    public static function checkAnalyze($analyzeParam, $params)
    {
        if ($analyzeParam != '-analyze') {
            return;
        }

        self::showHelpIfNeeded($params);
        self::checkConfigFolder($params);
        self::loadConfig();
        self::loadQueries();
        self::loadEnvironment($params);
        self::loadTablesInformation();
        self::getTopologicalOrder();
        self::analyzeGroups();

        self::endCheck();
    }

    public static function endCheck()
    {
        Output::print_msg(" END. ", "ANALYZE", true);
    }

    public static function loadEnvironment($params)
    {
        if (!array_key_exists(2, $params)) {
            Output::print_msg("You must pass environment name as a second param\n\n", "ERROR][ANALYZE", true);
        }

        $environmentName = $params[2];

        if (array_key_exists($environmentName, self::$config['environments'])) {
            Output::print_msg("Environment (". $environmentName .") exists in Config", "ANALYZE");
        } else {
            Output::print_msg("Environment (". $environmentName .") does NOT exists in Config", "ERROR][ANALYZE", true);
        }

        self::$config['environments'][$environmentName]['name'] = $environmentName;

        /** @var MysqlEnvironment environment */
        self::$environment = EnvironmentFactory::getEnvironment(self::$config['environments'][$environmentName]);

        if (!self::$environment instanceof MysqlEnvironment) {
            Output::print_msg("Environment is not analyzable because it is not an instance of MysqlEnv\n\n", "ANALYZE][WARNING");
        }
    }

    public static function showHelpIfNeeded($params)
    {
        if (empty($params) || count($params) < 3) {
            Output::print_msg("params: -analyze [config] [env]");
            InputHelp::showConfigFolders();
        }
    }

    public static function loadTablesInformation()
    {
        $tables = self::$environment->query('SHOW TABLES', true);
        $dbName = self::$environment->dbname;
        $index  = 'Tables_in_' . $dbName;
        $tablesInformation = array();

        foreach ($tables as $table) {
            $tableName = $table[$index];
            $metaInfoQuery = "SELECT * FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE information_schema.REFERENTIAL_CONSTRAINTS.TABLE_NAME = '" . $tableName . "'
             AND information_schema.REFERENTIAL_CONSTRAINTS.CONSTRAINT_SCHEMA = '".self::$environment->dbname."'";
            $info = self::$environment->query($metaInfoQuery, true);

            $tablesInformation[$tableName]['constraints'] = $info;
        }

        self::$tablesInformation = $tablesInformation;
    }

    public static function getTopologicalOrder()
    {
        $tablesReferences = array();

        foreach (self::$tablesInformation as $tableName => $tableInformation) {

            if (empty($tableInformation['constraints'])) {
                $tablesReferences[$tableName]['constraints'] = array();
            }

            foreach ($tableInformation['constraints'] as $constraint) {
                $linkedTable = $constraint['REFERENCED_TABLE_NAME'];

                if (!array_key_exists($tableName, $tablesReferences)) {
                    $tablesReferences[$tableName]['constraints'] = array();
                }

                if (!in_array($linkedTable, $tablesReferences[$tableName]['constraints'])) {
                    $tablesReferences[$tableName]['constraints'][] = $linkedTable;
                }
            }
        }

        self::$topologicalOrderTables = array();

        while (self::tablesReferencesIsNotEmpty($tablesReferences)) {
            $tablesWithNoReferences = self::getTablesReferencesWithNoReferences($tablesReferences);
            self::$topologicalOrderTables = array_merge(self::$topologicalOrderTables, $tablesWithNoReferences);
            self::$topologicalOrderTables = array_unique(self::$topologicalOrderTables);

            $tablesReferences = self::removeReferencesFromTablesReferences($tablesReferences, $tablesWithNoReferences);
        }

        $tablesWithNoReferences = self::getTablesReferencesWithNoReferences($tablesReferences);
        self::$topologicalOrderTables = array_merge(self::$topologicalOrderTables, $tablesWithNoReferences);
        self::$topologicalOrderTables = array_unique(self::$topologicalOrderTables);

        Output::print_msg("Tables in topological order from less dependencies to more", "ANALYZE");

        foreach (self::$topologicalOrderTables as $table) {
            Output::print_msg(" - " . $table);
        }

        Output::print_msg("\n\n");
    }

    public static function tablesReferencesIsNotEmpty($tablesReferences)
    {
        foreach ($tablesReferences as $tableName => $tableReferences) {
            if (!empty($tableReferences['constraints'])) {
                return true;
            }
        }

        return false;
    }

    public static function getTablesReferencesWithNoReferences($tablesReferences)
    {
        $result = array();

        foreach ($tablesReferences as $tableName => $tableReferences) {
            if (empty($tableReferences['constraints'])) {
                $result[] = $tableName;
            }
        }

        return $result;
    }

    public static function removeReferencesFromTablesReferences($tablesReferences, $references)
    {
        foreach ($tablesReferences as $tableName => $tableReferences) {
            foreach ($references as $reference) {
                if (in_array($reference, $tableReferences['constraints'])) {
                    $indexToRemove = array_search($reference, $tableReferences['constraints']);
                    unset($tablesReferences[$tableName]['constraints'][$indexToRemove]);
                }
            }
        }

        return $tablesReferences;
    }

    public static function analyzeGroups()
    {
        foreach (self::$config['groups'] as $groupName => $tableGroups) {
            self::analyzeGroup($groupName, $tableGroups);
        }
    }

    public static function analyzeGroup($groupName, $groupTables)
    {
        Output::print_msg("Topological order for group: " . $groupName, "ANALYZE");

        foreach (self::$topologicalOrderTables as $table) {
            if (in_array($table, $groupTables)) {
                $key = array_search($table, $groupTables);
                unset($groupTables[$key]);
                Output::print_msg($table);
            }
        }

        Output::print_msg("Orphan tables for group: " . $groupName, "ANALYZE");
        if (empty($groupTables)) {
            Output::print_msg("No Orphan tables for group: " . $groupName, "ANALYZE");
        } else {
            foreach ($groupTables as $table) {
                Output::print_msg(" - " . $table);
            }
        }

        Output::print_msg("-------------");
    }

}