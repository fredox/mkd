<?php

include_once('format.php');

class InputHelp {


    public static function getHelp($args, $config=false)
    {
        if (empty($args)) {
            static::showDefaultConfigEnvironments();
            Output::print_msg();
            Output::intro(2, true);
        }

        $commandArgs = implode(' ', $args);

        if (preg_match("/^(recipe|r):$/", $commandArgs)) {
            static::showRecipes();
            Output::intro(2, true);
        }

        if (preg_match("/^(recipe|r): -v$/", $commandArgs)) {
            static::showRecipes($verbose=true);
            Output::intro(2, true);
        }

        if (preg_match("/^cfg:$/", $commandArgs)) {
            static::showConfigFolders();
            Output::intro(2, true);
        }

        if (preg_match("/^cfg:([^\s:]+) ?$/", $commandArgs, $matches)) {
            static::showConfigEnvironments($matches[1]);
            Output::intro(2, true);
        }

        if (preg_match("/^cfg:([^\s:]+): ?$/", $commandArgs, $matches)) {
            static::showConfigGroups($matches[1]);
            Output::intro(2, true);
        }

        if (preg_match("/^cfg:([^\s:]+):: ?$/", $commandArgs, $matches)) {
            static::showQueries($matches[1]);
            Output::intro(2, true);
        }

        if (preg_match("/^cfg:([^\s:]+)::([^\s]+) ?$/", $commandArgs, $matches)) {
            static::showConfigEnvironments($matches[1]);
            Output::intro(2, true);
        }

        if (preg_match("/^cfg:([^\s]+):([^\s]+) ?$/", $commandArgs, $matches)) {
            static::showConfigEnvironments($matches[1]);
            Output::intro(2, true);
        }
    }

    public static function showConfigFolders()
    {
        $dirs = array_filter(glob('config/*'), 'is_dir');
        Output::print_msg("Available Configs: ", "HELP");

        foreach ($dirs as $dir) {
            $configDir = substr($dir, 7);
            Output::print_msg("\t- " . $configDir);
        }

        Output::intro(2);
    }

    public static function showDefaultConfigEnvironments()
    {
        $config = array();

        if (!is_file('config/default/config.php')) {
            Output::print_msg("There is no default config to load", "ERROR", true);
        }

        include_once('config/default/config.php');

        static::showEnvironments($config);
    }

    public static function showEnvironments($config)
    {
        if (!$config['environments']) {
            Output::print_msg("Environments key it is not set on default config", "ERROR", true);
        }

        Output::print_msg("Available Environments: ", "HELP");
        Output::intro();

        foreach ($config['environments'] as $environmentName => $environment) {
            if (!array_key_exists('type', $environment)) {
                Output::print_msg("Environment type is not set for: [". $environmentName ."]\n\n", "ERROR", true);
            }

            Output::print_msg("\t- " . $environmentName . ' (type:' . $environment['type'] . ')');

            if (array_key_exists('comments', $environment)) {
                Output::print_msg($environment['comments']);
            }
        }
    }

    public static function showConfigEnvironments($configPath)
    {
        $config = array();

        if (!is_dir('config/' . $configPath)) {
            Output::print_msg("config " . $configPath . " does NOT exists.\n\n");
            self::showConfigFolders();
            exit();
        }

        include_once('config/' . $configPath . '/config.php');

        static::showEnvironments($config);
    }

    public static function showConfigGroups($configPath)
    {
        $config = array();

        include_once('config/' . $configPath . '/config.php');

        if ((!array_key_exists('groups', $config)) || !$config['groups']) {
            Output::print_msg("groups key it is not set on config [". $configPath ."]\n\n", "ERROR", true);
        }

        Output::print_msg("Available Groups: ", "HELP");

        foreach ($config['groups'] as $groupName => $tables) {
            $defaultTxt = (in_array($groupName, $config['groups-to-import'])) ? ' [DEFAULT]' : '';
            Output::print_msg("\n  - " . $groupName . ' (with tables -> ' . implode(',', $tables) . ')' . ' ' . $defaultTxt);
        }

        Output::intro();
    }

    public static function showQueries($configPath)
    {
        $queries = array();

        include_once('config/' . $configPath . '/queries.php');

        if (empty($queries)) {
            Output::print_msg("Queries are empty", "ERROR", true);
        }

        Output::print_msg("Available queries", "HELP");

        foreach ($queries as $queryName => $query) {
            Output::print_msg(" - " . $queryName);
        }
    }

    public static function showRecipes($verbose=false)
    {
        $files = array_filter(array_merge(glob('recipes/*/*'),glob('recipes/*')), 'is_file');
        Output::print_msg("Available recipes: ", "HELP");
        Output::intro();

        foreach ($files as $file) {
            $recipe = substr($file, 8);
            $recipeContent = file($file);

            if ($verbose) {
                squaredText($recipe, $indented=1);
                foreach ($recipeContent as $recipeContentLine) {
                    Output::print_msg("\t\t" . $recipeContentLine);
                }
                Output::intro(3);
            } else {
                Output::print_msg(whiteFormat($recipe) . " " . trim($recipeContent[0], "\n-"));
            }

        }

        Output::intro(2);
    }
}