<?php

include_once('ReplaceTransformation.php');
include_once('LazyTransformation.php');
include_once('SchemaTransformation.php');
include_once('KeysTransformation.php');
include_once('DropTransformation.php');
include_once('IgnoreTransformation.php');
include_once('RollbackTransformation.php');

class TransformationFactory {
    public static function getTransformation($transformationId, $config)
    {
        $transformationClassName = ucfirst(strtolower($transformationId)) . 'Transformation';

        if (!class_exists($transformationClassName)) {
            Output::print_msg("[TRANSFORMATION] Class " . $transformationClassName . " does not exists", "ERROR");
            exit;
        }

        return new $transformationClassName($config);
    }
}