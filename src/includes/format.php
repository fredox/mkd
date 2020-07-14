<?php

function whiteFormat($txt)
{
    return "\033[0;30m\033[47m".$txt."\033[0m";
}

function greenFormat($txt)
{
    return "\033[0;30m\033[42m".$txt."\033[0m";
}

function redFormat($txt)
{
    return "\033[0;30m\033[41m".$txt."\033[0m";
}

function magentaFormat($txt)
{
    return "\033[0;30m\033[35m".$txt."\033[0m";
}

function blueFormat($txt)
{
    return "\033[0;30m\033[34m".$txt."\033[0m";;
}

function warningFormat($txt)
{
    return "\033[0;30m\033[43m".$txt."\033[0m";;
}

function squaredText($text, $indented=2)
{
    if (strlen($text) > 70) {
        $text = substr($text, 0, 66) . '...';
    }

    $length  = strlen($text);
    $topDown = '+--' . str_repeat('-', $length) . '--+';
    $middle  = '|  ' . $text . '  |';

    $indentation = str_repeat("\t", $indented);

    Output::print_msg($indentation . $topDown . "\n" . $indentation . $middle . "\n" . $indentation . $topDown . "\n\n");
}