<?php

include_once('MysqlToDryrunMonad.php');
include_once('Monad.php');

class RawToDryrunMonad extends MysqlToDryrunMonad
{
    /**
     * @param $data
     * @param Environment $sourceEnvironment
     * @param Environment $targetEnvironment
     * @param array $transformations
     * @return array
     */
    public function bind($data, Environment $sourceEnvironment, Environment $targetEnvironment, $transformations = array())
    {
        $targetEnvironment->rawQueries = $sourceEnvironment->rawQueries;

        return [];
    }
}