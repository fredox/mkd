<?php

require_once('Environments/EnvironmentFactory.php');
require_once ('Transformation/TransformationFactory.php');
require_once('Monad/MonadFactory.php');
require_once('Condition.php');

function transfer($sourceEnvironment, $targetEnvironment, $queries, $key, $config)
{
    print_key($key);

    $transformations = array();

    $config['environments'][$sourceEnvironment]['configPath'] = $config['configPath'];
    $config['environments'][$targetEnvironment]['configPath'] = $config['configPath'];

    $sourceEnvironment = EnvironmentFactory::getEnvironment($config['environments'][$sourceEnvironment]);
    $targetEnvironment = EnvironmentFactory::getEnvironment($config['environments'][$targetEnvironment]);

    $queriesIndexes       = array_keys($queries);
    $queriesToProcess     = [];
    $groupsIndexToProcess = $config['groups-to-import'];
    $cleanIndexGroupsToProcess = [];

    foreach ($groupsIndexToProcess as $group) {
        if (Condition::isConditionalIndex($group)) {
            try {
                if (Condition::conditionPass($group, $sourceEnvironment, $targetEnvironment, $queries, $key)) {
                    Output::print_msg("Condition [" . Condition::getQuery($group). "] pass. Including [" . Condition::getIndex($group) . "] in import", "INFO");
                    $cleanIndexGroupsToProcess[] = Condition::getIndex($group);
                } else {
                    Output::print_msg("Condition [" . Condition::getQuery($group). "] DO NOT pass. Skipping [" . Condition::getIndex($group) . "] from import", "INFO");
                }
            } Catch (Exception $e) {
                Output::print_msg($e->getMessage(), "ERROR");
            }

            continue;
        } else {
            $cleanIndexGroupsToProcess[] = $group;
        }
    }

    foreach ($cleanIndexGroupsToProcess as $groupIndex){
        foreach ($config['groups'][$groupIndex] as $queryIndexToProcess) {
            if (Condition::isConditionalIndex($queryIndexToProcess)) {
                if (Condition::conditionPass($queryIndexToProcess, $sourceEnvironment, $targetEnvironment, $queries, $key)) {
                    $queriesToProcess[Condition::getIndex($queryIndexToProcess)] = Condition::getQuery($queryIndexToProcess);
                }
                continue;
            }
            if (in_array($queryIndexToProcess, $queriesIndexes)) {
                $queriesToProcess[$queryIndexToProcess] = $queries[$queryIndexToProcess];
            }
        }
    }

    Output::print_msg("Collecting data from [" . $sourceEnvironment->name . "]\n", "INFO");
    $data = $sourceEnvironment->get($queriesToProcess, $key);

    if (array_key_exists('transformations', $config['execution'])) {
        foreach ($config['execution']['transformations'] as $transformationId) {
            $transformations[] = TransformationFactory::getTransformation($transformationId, $config);
        }
    }

    /** @var Monad $monad */
    $monad = MonadFactory::getMonad($sourceEnvironment, $targetEnvironment, $config);

    $data = $monad->bind($data, $sourceEnvironment, $targetEnvironment, $transformations);

    Output::print_msg("Transfering data to [" . $targetEnvironment->name . "]\n", "INFO");
    $targetEnvironment->put($data);
}

function print_key($key)
{
    if (strlen($key) > 70) {
        $key = substr($key, 0, 66) . '...';
    }

    $text    = 'KEY: ' . $key;
    $length  = strlen($text);
    $topDown = '+--' . str_repeat('-', $length) . '--+';
    $middle  = '|  ' . $text . '  |';

    Output::print_msg($topDown);
    Output::print_msg($middle);
    Output::print_msg($topDown);
}
