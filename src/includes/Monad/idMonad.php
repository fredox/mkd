<?php

include_once('Monad.php');

class idMonad implements Monad
{
    public $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function unit($data, Environment $targetEnvironment)
    {
        return $data;
    }

    /**
     * @param $data
     * @param Environment $sourceEnvironment
     * @param Environment $targetEnvironment
     * @param $transformations
     * @return mixed
     */
    public function bind($data, Environment $sourceEnvironment, Environment $targetEnvironment, $transformations)
    {
        if (!empty($transformations)) {
            /** @var Transformation $transformation */
            foreach ($transformations as $transformation) {
                $data = $transformation->transform($data, $sourceEnvironment, $targetEnvironment);
            }
        }

        return $this->unit($data, $targetEnvironment);
    }
}