<?php

include_once('Transformation.php');

class IgnoreTransformation implements Transformation
{
    public function transform($data, Environment $sourceEnvironment, Environment $targetEnvironment)
    {
        Output::print_msg("[TRANSFORMATION][REPLACE] Changing to replace mode", "INFO");
        $targetEnvironment->operation = 'INSERT IGNORE';

        return $data;
    }
}