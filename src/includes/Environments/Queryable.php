<?php


interface Queryable
{
    public function query($query, $fetch=false);
}