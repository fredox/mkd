<?php

interface Environment
{
    public function put($data);
    public function get($queries, $key);
    public function getType();
    public function getName();
    public function describe($dataIndex);
}