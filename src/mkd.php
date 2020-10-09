<?php

ini_set('memory_limit', '512M');

error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);

include_once('includes/InputHelp.php');
include_once('includes/Health.php');
include_once('includes/Analyze.php');
include_once('includes/Clean.php');
include_once('includes/Alias.php');
include_once('includes/Condition.php');
include_once('includes/Timer.php');
include_once('includes/Help.php');
include_once('includes/Install.php');
include_once('includes/Logo.php');
include_once('includes/Output.php');
include_once('includes/JsonTableConverter.php');

array_shift($argv);

Script::open();

if ($argv[0] != '-i') {
    Mikado::execute($argv);
} else {
    Mikado::interactive();
}


class Mikado {

    public static function start($args)
    {
        if (!preg_match("/^(?:recipe|r):(.*)$/", $args[0], $matches)) {
            static::run($args);
        } else {
            $recipe = $matches[1];
            array_shift($args);
            static::bakeRecipe($args, $recipe);
        }
    }

    public static function execute($args)
    {
        Logo::startLogo();

        Install::checkCreateInstall($args);
        if (Script::isOpen()) { Help::checkHelp($args); }

        $params = Alias::checkAlias($args);

        if (array_key_exists(0, $params)) {
            if (Script::isOpen()) { Health::checkHealth($params[0], $args); }
            if (Script::isOpen()) { Analyze::checkAnalyze($params[0], $args); }
            if (Script::isOpen()) { Clean::checkClean($params[0]); }
        }

        if (Script::isOpen()) { InputHelp::getHelp($params); }
        if (Script::isOpen()) { Output::print_msg("Timer starts.", "INFO"); }
        if (Script::isOpen()) { Timer::start('script'); }
        if (Script::isOpen()) { Mikado::start($params); }
        if (Script::isOpen()) { Output::print_msg("Timer, Elapsed: " . round(Timer::elapsed('script'),1) . " seconds\n\n", "INFO"); }
        if (Script::isOpen()) { Output::print_msg(" Memory usage: " . self::convert(memory_get_usage(true))); }


        Logo::endLogo();
    }

    public static function convert($size)
    {
        $unit=array('b','kb','mb','gb','tb','pb');
        return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    }

    public static function checkConfig($config, $queries, $configPath)
    {
        if (empty($config)) {
            Output::print_msg("Config file is empty: " . $configPath . "/config.php\n", "ERROR", true);
        }

        $cleanGroupsToImport = array();

        foreach ($config['groups-to-import'] as $group) {
            if (Condition::isConditionalIndex($group)) {
                $cleanGroupsToImport[] = Condition::getIndex($group);
                $config['groups'][$group][] = Condition::getQuery($group);
            }
        }

        foreach ($cleanGroupsToImport as $group) {

            if (!array_key_exists($group, $config['groups'])) {
                Output::print_msg($group . " does not exist as index in groups config", "ERROR", true);
            }

            foreach ($config['groups'][$group] as $queryIndex) {
                if (!array_key_exists($queryIndex, $queries)) {
                    Output::print_msg($queryIndex . " does not exist as index in query files", "ERROR", true);
                }
            }
        }
    }

    public static function run($args)
    {
        $executed = static::executeCommandIfNeeded($args);

        if ($executed) return;

        list($configPath, $customGroupTag, $params) = static::getConfigPath($args);

        Output::print_msg($configPath, "CONFIG PATH");

        $config = $queries = array();

        require($configPath . '/config.php');

        $config = static::getGroupsToImport($customGroupTag, $config);

        require($config['queries-file']);

        static::checkConfig($config, $queries, $configPath);

        Output::print_msg($config['queries-file'], "QUERIES FILE");

        require_once('includes/transfer.php');
        require_once('includes/Input.php');
        require_once('includes/format.php');

        $config['all-queries-indexes'] = array_keys($queries);
        $config['configPath'] = $configPath;

        $config = Input::get($config, $params);

        $sourceEnvironment = $config['execution']['source_environment'];
        $targetEnvironment = $config['execution']['target_environment'];
        $key = $config['execution']['key'];

        Output::intro();
        Output::print_msg(whiteFormat(" ███ ") . " TRANSFER BEGIN");
        Output::intro();

        $keys = static::getKeys($key, $config);

        foreach ($keys as $key) {
            transfer($sourceEnvironment, $targetEnvironment, $queries, $key, $config);
        }

        Output::intro();
        Output::print_msg(whiteFormat("     ") . " TRANSFER END");
    }

    public static function executeCommandIfNeeded($args)
    {
        $executedCommand = false;

        if (preg_match('/^cfg:([a-zA-Z0-9]+)$/', $args[0], $matches)) {
            $configPath = $matches[1];
            require('config/' . $configPath . '/config.php');
        } else {
            Output::print_msg("No command detected", "INFO");
            return;
        }

        if ($args[1] == '-cmd' && empty($args[2])) {
            Output::intro();
            Output::print_msg("Available commands:", "INFO][COMMAND");
            Output::intro();

            foreach ($config['commands'] as $commandAlias => $shellCommand) {
                Output::print_msg(magentaFormat($commandAlias));
                Output::print_msg("\t" . blueFormat($shellCommand));
            }

            return true;
        }

        if ($args[1] == '-cmd' && !empty($args[2])) {
            Output::print_msg("Command detected: " . $args[2], "INFO");
            $executedCommand = true;
            $command = $args[2];

            if (array_key_exists($command, $config['commands'])) {
                Output::print_msg("Executing command " . $command, "INFO");

                $realCommand = $config['commands'][$command];

                // fill the command with $[0-9]+ params
                $commandParams = array_slice($args, 3);
                foreach ($commandParams as $index => $param) {
                    $realCommand = str_replace("$" . ($index), $param, $realCommand);
                }

                Output::print_msg("Executing command: " . $realCommand, "INFO");

                exec($realCommand, $outputLines, $result);
                foreach ($outputLines as $outputLine) {
                    Output::print_msg($outputLine, "COMMAND][OUTPUT");
                }
                Output::print_msg($result, "COMMAND][EXIT RESULT");
            } else {
                Output::print_msg("Command " . $args[2] . " not available in config. Found: " . implode(array_keys($config['commands']), ','), "ERROR");
            }
        }

        return $executedCommand;
    }

    public static function getConfigPath($args)
    {
        $customGroupTags = false;

        if (preg_match('/^cfg:(.*)$/', $args[0], $matches)) {

            $newArgs = array();
            $path    = $matches[1];

            if (preg_match('/^([^:]+):([^:]+)$/', $matches[1],$matchesGroup)) {
                $customGroupTags = array('type' => 'custom-group', 'value' => $matchesGroup[2]);
                $path = $matchesGroup[1];
            }

            if (preg_match('/^([^:]+)::(.+)$/', $matches[1],$matchesGroup)) {
                $customGroupTags = array('type' => 'custom-tmp-group', 'value' => $matchesGroup[2]);
                $path = $matchesGroup[1];
            }

            $configPath        = 'config/' . $path;
            $configFileAndPath = $configPath.'/config.php';

            if (!is_file($configFileAndPath)) {
                Output::print_msg("\n\n");
                Output::print_msg("Specified config file (" . $configFileAndPath . ") does NOT exists!", "ERROR", true);
            }

            // Remove cfg param from array params.
            foreach ($args as $key=>$arg) {
                if ($key != 0) {
                    $newArgs[] = $arg;
                }
            }

            return array($configPath, $customGroupTags, $newArgs);
        }

        Output::print_msg("Bad Params. Config path not detected\n\n", "ERROR", true);
    }

    public static function getGroupsToImport($customGroupTags, $config)
    {
        if ($customGroupTags === false) {
            return $config;
        }

        if ($customGroupTags['type'] == 'custom-group') {
            Output::print_msg("Selecting custom group", "INFO");
            $config['groups-to-import'] = explode(',', $customGroupTags['value']);
            return $config;
        }

        if ($customGroupTags['type'] == 'custom-tmp-group') {
            Output::print_msg("Creating custom group on the fly [" . $customGroupTags['value'] . "]", "INFO");
            $tmpGroupName = 'tmp-' . str_replace(',', '-', $customGroupTags['value']);
            $config['groups'][$tmpGroupName] = explode(',', $customGroupTags['value']);
            $config['groups-to-import'] = array($tmpGroupName);
        }

        return $config;
    }

    public static function bakeRecipe($args, $recipe)
    {
        $filePath = 'recipes/' . $recipe;

        if (!is_file($filePath)) {
            Output::print_msg("Specified recipe file (" . $recipe . ") does NOT exists!", "ERROR", true);
        }

        $steps  = array();
        $handle = fopen($filePath, "r");

        Output::print_msg("Baking a delicious recipe...", "INFO");

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);

            if (strpos($line, '--') === 0)
                continue;

            if (empty($line))
                continue;

            $stepParams = $line;
            foreach ($args as $index => $arg) {
                $stepParams = str_replace('$' . $index, $arg, $stepParams);
            }

            $steps[] = explode(' ', $stepParams);
        }

        fclose($handle);

        $nSteps = count($steps);
        $currentStep = 1;

        try {

            foreach ($steps as $stepArguments) {
                Output::intro();
                Output::print_msg(whiteFormat("  Step " . $currentStep . " of " . $nSteps . "  [args] " . implode(" ", $stepArguments)));
                Output::intro();
                static::start($stepArguments);
                $currentStep++;
            }

        } Catch (Exception $e) {
            Output::print_msg($e->getMessage(), "UNEXPECTED ERROR", true);
        }

        Output::intro(1);
        Output::print_msg(whiteFormat(" > > RECIPE END < < "));

    }

    public static function getKeys($key, $config)
    {
        if (!array_key_exists('compact-mode', $config)) {
            Output::print_msg("You must define compact-mode in config.", "ERROR", true);
        }

        $keys = explode(',', $key);
        $keys = array_unique($keys);

        if ($config['compact-mode'] === true) {
            foreach ($keys as $i=>$key) {
                if (!preg_match("/^[0-9]+$/", $key)) {
                    $keys[$i] = "'" . $key . "'";
                }
            }
            $keys = array(implode(',', $keys));
        }

        return $keys;
    }

    public static function interactive()
    {
        $quitParams = ['quit', 'exit', 'adios'];

        Output::print_msg("Mikado interactive >", "INFO", false);
        Output::intro();

        do {
            Script::open();

            $cmd = trim((readline(magentaFormat("\n\t mkd> "))));
            readline_add_history($cmd);
            $params = explode(' ', $cmd);

            if (!in_array($params[0], $quitParams)) {
                Mikado::execute($params);
            }

        } while (!in_array($params[0], $quitParams));

        Output::print_msg("Mikado interactive END. See you soon!\n\n", "INFO", false);
    }
}

class Script {
    public static $status = 'NONE';

    public static function open() {
        self::$status = 'OPEN';
    }

    public static function close() {
        self::$status = 'CLOSED';
    }

    public static function isClosed() {
        return self::$status == 'CLOSED';
    }

    public static function isOpen() {
        return self::$status == 'OPEN';
    }
}


