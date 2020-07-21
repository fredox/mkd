<?php

include_once('idMonad.php');

require_once('MysqlToDryrunMonad.php');
require_once('MysqlToMysqlMonad.php');
require_once('PgsqlToDryrunMonad.php');
require_once('PgsqlToPgsqlMonad.php');
require_once('DryrunToMysqlMonad.php');
require_once('RawToDryrunMonad.php');
require_once('RawToMysqlMonad.php');
require_once('SerializeddatafileToMysqlDryrunMonad.php');
require_once('SerializeddatafileToPgsqlDryrunMonad.php');
require_once('SerializeddatafileToMysqlMonad.php');
require_once('SerializeddatafileToPgsqlMonad.php');
require_once('RawToSerializeddatafileMonad.php');
require_once('SqlToSqlMonad.php');
require_once('RawToPgsqlMonad.php');
require_once('MysqlToPgsqlMonad.php');

class MonadFactory
{
    public static function getMonad(Environment $sourceEnvironment, Environment $targetEnvironment, $config)
    {
        $sourceEnvironmentType = ucfirst(strtolower($sourceEnvironment->getType()));
        $targetEnvironmentType = ucfirst(strtolower($targetEnvironment->getType()));

        $monadNameClass = $sourceEnvironmentType . 'To' . $targetEnvironmentType . 'Monad';

        if (!class_exists($monadNameClass)) {
            Output::print_msg("Default adapter id with name: " . $monadNameClass, "INFO");
            return new idMonad($config);
        }
        Output::print_msg("Adapter found:" . $monadNameClass, "INFO");
        return new $monadNameClass($config);
    }
}