<?php

include_once ('MysqlToDryrunMonad.php');

class SerializeddatafileToMysqlDryrunMonad extends MysqlToDryrunMonad
{
    /**
     * @param $data
     * @param SerializedDataFileEnvironment|Environment $sourceEnvironment
     * @param Environment|DryRunEnvironment $targetEnvironment
     * @param array $transformations
     * @return array
     */
    public function bind($data, Environment $sourceEnvironment, Environment $targetEnvironment, $transformations = array())
    {
        $data = parent::bind($data, $sourceEnvironment, $targetEnvironment, $transformations);

        $targetEnvironment->rawQueries = $sourceEnvironment->getRawQueries();

        return $data;
    }
}