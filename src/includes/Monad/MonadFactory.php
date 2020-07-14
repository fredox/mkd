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

class MonadFactory
{
    public static function getMonad(Environment $sourceEnvironment, Environment $targetEnvironment, $config)
    {
        $sourceEnvironmentType = ucfirst(strtolower($sourceEnvironment->getType()));
        $targetEnvironmentType = ucfirst(strtolower($targetEnvironment->getType()));

        if ($targetEnvironment->getType() == 'DryRun') {
            $virtualEnvironmentIndex = $config['environments'][$targetEnvironment->name]['targetEnvironment'];
            $subType = $config['environments'][$virtualEnvironmentIndex]['type'];
            $targetEnvironmentType = ucfirst(strtolower($subType)) . $targetEnvironmentType;
        }

        $monadNameClass = $sourceEnvironmentType . 'To' . $targetEnvironmentType . 'Monad';

        if (!class_exists($monadNameClass)) {
            return new idMonad($config);
        }

        return new $monadNameClass($config);
    }
}