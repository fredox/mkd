<?php

interface Monad
{
	public function unit($data, Environment $targetEnvironment);
    public function bind($data, Environment $sourceEnvironment, Environment $targetEnvironment, $transformations);
    public function getConfig();
}