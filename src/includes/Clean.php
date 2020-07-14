<?php

Class Clean {

    public static $configDir;
    public static $queries;
    public static $config;

    public static function checkClean($cleanParam)
    {
        if ($cleanParam != '-clean-io') {
            return;
        }

        Output::print_msg("START", "CLEAN");

        self::deleteIoFiles();
        self::endClean();
    }

    public static function deleteIoFiles()
    {
        $files = glob(Input::INPUT_OUTPUT_FOLDER . '/*'); // get all file names
        foreach($files as $file){ // iterate files
            if(is_file($file)) {
                Output::print_msg("Deleting file " . $file, "CLEAN");
                unlink($file); // delete file
            }
        }

        Output::print_msg("\n");
    }

    public static function endClean()
    {
        Output::print_msg("END", "CLEAN", true);
    }
}