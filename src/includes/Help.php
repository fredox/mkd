<?php

include_once('Alias.php');

class Help {
    public static function checkHelp($params)
    {
        if (!empty($params) && ($params[0] == '-help' || $params[0] == '-h')) {
            Alias::showAliasInfo();
        }
    }
}