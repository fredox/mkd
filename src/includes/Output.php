<?php

class Output {
    public static $ident = "\n\t";

    public static function print_msg($message, $type=false, $exit=false) {
        $msg = self::$ident;
        $msg .= ($type) ? "[" . $type . "] " : "";
        $msg .= $message;

        Output::out_print($msg);

        if ($exit) {
            self::intro();
            exit();
        }
    }

    public static function intro($n=1, $exit=false) {
        for ($i=0;$i<$n;$i++) {
            Output::print_msg("\n");
        }

        if($exit) {
            Script::close();
        }
    }

    public static function out_print($msg) {
        // TODO: Make future possibility to put output in a file
        // TODO: or as an HTML response.
        echo $msg;
    }
}