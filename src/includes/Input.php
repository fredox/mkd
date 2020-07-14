<?php
class Input
{
    const INPUT_OUTPUT_FOLDER = 'io';

    public static function get($config, $params)
    {
        if (!array_key_exists('environments', $config)) {
            static::inputError('There is no environments defined. Look at your config file.', $config);
        }

        // SOURCE ENVIRONMENT
        list($params, $environment) = static::getEnvironment($params, $config);
        $config['execution']['source_environment'] = $environment;
        Output::print_msg($environment, "SOURCE ENV");

        // TRANSFORMATIONS
        if (static::optionalParamNext($params)) {
            list($params, $config['execution']['transformations']) = static::getTransformations($params);
        }

        // TARGET ENVIRONMENT
        list($params, $environment) = static::getEnvironment($params, $config);
        $config['execution']['target_environment'] = $environment;
        Output::print_msg($environment, "TARGET ENV");


        // KEY
        list ($params, $config['execution']['key']) = static::getKey($params);

        Output::print_msg(implode(',', $config['groups-to-import']), "GROUPS TO IMPORT");

        $config = static::setNamesToEnvironments($config);

        return $config;

    }

    public static function setNamesToEnvironments($config)
    {
        foreach ($config['environments'] as $name => $environment) {
            $config['environments'][$name]['name'] = $name;
        }

        return $config;
    }

    public static function getEnvironment($params, $config)
    {
        $environment = array_shift($params);
        $availableEnvironments = array_keys($config['environments']);

        if (!in_array($environment, $availableEnvironments)) {
            static::inputError('Unknown Environment [' . $environment . ']', $config);
        }

        return array($params, $environment);
    }

    public static function optionalParamNext($params)
    {
        if (empty($params)) {
            return false;
        }

        return (strpos($params[0], '-') === 0);
    }

    public static function getTransformations($params)
    {
        $param = array_shift($params);
        $transformations = explode(',', substr($param, 1));

        return array($params, $transformations);
    }

    public static function inputError($msg, $config, $verbose = false)
    {
        Output::print_msg(redFormat($msg), "ERROR][INPUT");
        Output::print_msg("\n\n\n");

        if ($verbose) {
            print_r($config);
            print_r($verbose);
        }
        exit(0);
    }

    public static function getKey($params)
    {
        $key = array_shift($params);

        if ((strpos($key, '.') !== false) && (is_file(static::INPUT_OUTPUT_FOLDER .'/' . $key))) {
            $key = file_get_contents(static::INPUT_OUTPUT_FOLDER .'/' . $key);
            $key = trim($key);
        }

        return array($params, $key);
    }

}
