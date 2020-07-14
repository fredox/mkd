<?php

interface Transformation {
    public function transform($data, Environment $sourceEnvironment, Environment $targetEnvironment);
}