<?php

include_once('MysqlToMysqlMonad.php');
include_once('Monad.php');

class DryrunToMysqlMonad extends MysqlToMysqlMonad implements Monad
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

        $data = parent::bind($data, $sourceEnvironment, $targetEnvironment, $transformations);

        return $data;
    }
}