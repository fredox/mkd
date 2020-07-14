<?php

include_once('Monad.php');
include_once('PgsqlToPgsqlMonad.php');

class PgsqlToDryrunMonad extends PgsqlToPgsqlMonad implements Monad
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
        $virtualTargetEnvironment = $this->getVirtualTargetEnvironment($sourceEnvironment, $targetEnvironment);

        $data = parent::bind($data, $sourceEnvironment, $virtualTargetEnvironment, $transformations);

        $targetEnvironment->rawQueries = $sourceEnvironment->rawQueries;

        return $data;
    }

    /**
     * @param Environment $sourceEnvironment
     * @param Environment $targetEnvironment
     * @return DryRunEnvironment|KeyFileEnvironment|MysqlEnvironment
     */
    public function getVirtualTargetEnvironment(Environment $sourceEnvironment, Environment $targetEnvironment)
    {
        $dryRunEnvironmentName = $targetEnvironment->getName();

        $config = $this->getConfig();

        if (array_key_exists('targetEnvironment', $config['environments'][$dryRunEnvironmentName])) {
            $virtualTargetEnvironmentName = $config['environments'][$dryRunEnvironmentName]['targetEnvironment'];
            $virtualTargetEnvironmentConfig = $config['environments'][$virtualTargetEnvironmentName];
            $virtualEnvironment = EnvironmentFactory::getEnvironment($virtualTargetEnvironmentConfig);
        } else {
            $virtualEnvironment = $sourceEnvironment;
        }

        $this->checkVirtualEnvironment($virtualEnvironment);

        Output::print_msg("[MysqlToDryRun][VirtualEnvironment] " . $virtualEnvironment->name, "INFO");

        return $virtualEnvironment;
    }

    public function checkVirtualEnvironment(Environment $virtualEnvironment)
    {
        if (!$virtualEnvironment instanceof PgsqlEnvironment) {
            Output::print_msg("[DryRun] VirtualEnvironment (targetEnvironment in Dry run or Source Environment) must be ", "ERROR");
            Output::print_msg("[DryRun] of type PgsqlEnvironment, [" . get_class($virtualEnvironment) . "] given with environment name: ", "ERROR");
            Output::print_msg($virtualEnvironment->getName(), "ERROR", true);
        }
    }

    public function executeRawQueriesAtFirst($targetEnvironment)
    {
        // Dry run must not execute raw queries because it will be written in some file.
    }
}