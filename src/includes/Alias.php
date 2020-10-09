<?php

Class Alias {

    public static function checkAlias($params)
    {
        $aliases = self::getAliases();
        $aliasRegexp = "/^(" . implode("|", array_keys($aliases)) . ")$/";

        if (!empty($params) && $params[0] == '-alias') {
            self::showAliasInfo();
        }

        if (!empty($params) && (preg_match($aliasRegexp, $params[0], $matches))) {
            Output::print_msg("Detected alias: [" . $matches[0] . "]", "ALIAS");
            $aliasIndex = $matches[0];
            Output::print_msg("Replacing alias by: [" . $aliases[$aliasIndex]['slug'] . "]");
            $params = self::getParams($aliases[$aliasIndex]['slug'], $params);
        }

        return $params;
    }

    public static function getParams($aliasSlug, $originalParams)
    {
        // remove alias itself
        array_shift($originalParams);
        $newParams = explode(' ', $aliasSlug);

        foreach ($originalParams as $param) {
            $newParams[] = $param;
        }

        return $newParams;
    }

    public static function showAliasInfo()
    {
        $aliases = self::getAliases();

        Output::print_msg("+---------");

        foreach ($aliases as $alias => $aliasInfo) {
            Output::print_msg("| " . whiteFormat(" " . $alias . " ") . "\t" . $aliasInfo['help']);
            Output::print_msg("|  \tReplaced command slug > " . $aliasInfo['slug']);
            Output::print_msg("+ ---------");
        }

        Output::intro(1, true);
    }

    public static function getAliases()
    {
        $aliases = [];
        $files = array_diff(scandir('alias'), array('.', '..'));

        foreach ($files as $file) {
            $aliases = array_merge($aliases, parse_ini_file('alias/' . $file));
        }

        return $aliases;
    }
}