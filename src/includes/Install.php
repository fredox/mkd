<?php

class Install {

    public static $aliases = [];
    public static $configs = [];
    public static $recipes = [];

    public static $excludedDirs = ['.', '..', '.DS_Store', '.gitkeep'];

    public static $installFile = 'install';

    public static function checkCreateInstall($params)
    {
        if (!empty($params)) {
            if ($params[0] == '-make-install') {
                if (isset($params[1])) {
                    self::$installFile = $params[1];
                }
                self::toInstallFile();
            }

            if ($params[0] == '-install') {
                self::installs();
            }

            if ($params[0] == '-reset-install') {
                self::reset();
            }
        }
    }

    public static function reset()
    {
        self::rmDirectoryContent('alias');

        $configDirs = array_diff(scandir('config'), self::$excludedDirs);

        if ($configDirs) {
            foreach ($configDirs as $dir) {
                Output::print_msg("deleting content -->" . $dir);
                self::rmDirectoryContent('config/' . $dir);
                rmdir('config/' . $dir);
            }
        }

        $recipeDirs = array_diff(scandir('recipes'), self::$excludedDirs);

        if ($recipeDirs) {
            foreach ($recipeDirs as $dir) {
                self::rmDirectoryContent('recipes/' . $dir);
                rmdir('recipes/' . $dir);
            }
        }

        Output::print_msg("Reset OK", "INSTALL", true);
    }

    public static function rmDirectoryContent($dir)
    {
        $aliasFiles = array_diff(scandir($dir), self::$excludedDirs);

        foreach ($aliasFiles as $file) {
            unlink($dir . '/' . $file);
        }
    }

    public static function installs()
    {
        Output::print_msg("Start Install", "INSTALL");
        $installFiles = array_diff(scandir('install'), self::$excludedDirs);

        if (empty($installFiles)) {
            Output::print_msg("There is no file(s) in folder install", "INSTALL", true);
        }

        foreach ($installFiles as $installFile) {
            Output::print_msg("Recovering data from installation: " . explode('.', $installFile)[0], "INSTALL");
            $data = file_get_contents('install/' . $installFile);
            $cleanData = unserialize($data);

            Output::print_msg("Adding aliases", "INSTALL");
            file_put_contents('alias/' . explode('.', $installFile)[0] . '.ini' , $cleanData['aliases']);

            Output::print_msg("Adding configs ans queries", "INSTALL");
            foreach ($cleanData['configs'] as $dir => $file) {
                if (!is_dir('config/' . $dir)) {
                    mkdir('config/' . $dir);
                }
                Output::print_msg("Adding config and queries for [" . $dir . "]", "INSTALL");
                file_put_contents('config/' . $dir . '/config.php' , $file['config']);
                file_put_contents('config/' . $dir . '/queries.php', $file['queries']);
            }

            Output::print_msg("Adding recipes", "INSTALL");
            foreach ($cleanData['recipes'] as $dir => $files) {
                if (!is_dir('recipes/' . $dir)) {
                    mkdir('recipes/' . $dir);
                }
                Output::print_msg("Adding recipes for [" . $dir . "]", "INSTALL");
                foreach ($files as $filename => $fileContent) {
                    file_put_contents('recipes/' . $dir . '/' . $filename , $fileContent);
                }

            }
        }
        Output::print_msg("End Install", "INSTALL", true);
    }

    public static function toInstallFile()
    {
        self::getAlias();
        self::getConfigs();
        self::getRecipes();

        $install = [
            'aliases' => self::$aliases,
            'configs' => self::$configs,
            'recipes' => self::$recipes
        ];

        file_put_contents('install/' . self::$installFile . '.serialized', serialize($install));

        Output::print_msg("Make Installation End\n\n", "MAKE-INSTALL");
        Output::print_msg("Installation file saved at: " . magentaFormat('install/' . self::$installFile . '.serialized'), "MAKE-INSTALL", true);
    }

    public static function getAlias()
    {
        $files = array_diff(scandir('alias'), self::$excludedDirs);

        foreach ($files as $file) {
            Output::print_msg("Exporting alias files", "MAKE-INSTALL");
            self::$aliases[] = file_get_contents('alias/' . $file);
        }
    }

    public static function getConfigs()
    {
        $directories = array_diff(scandir('config'), self::$excludedDirs);

        foreach ($directories as $dir) {
            Output::print_msg("exporting queries config from [" . $dir . "]", "MAKE-INSTALL");
            self::$configs[$dir]['config'] = file_get_contents('config/' . $dir . '/config.php');
            self::$configs[$dir]['queries'] = file_get_contents('config/' . $dir . '/queries.php');
        }
    }

    public static function getRecipes()
    {
        $directories = array_diff(scandir('recipes'), self::$excludedDirs);
        Output::print_msg("exporting recipes", "MAKE-INSTALL");
        foreach ($directories as $dir) {
            $files  = array_diff(scandir('recipes/' . $dir), self::$excludedDirs);

            foreach ($files as $file) {
                Output::print_msg("exporting recipe " . $file, "MAKE-INSTALL");
                self::$recipes[$dir][$file] = file_get_contents('recipes/' . $dir . '/' . $file);
            }

        }
    }

    public static function removeGitKeep($arrayFileDir)
    {
        $result = [];

        foreach ($arrayFileDir as $file) {
            if ($file != '.gitkeep') {
                $result[] = $file;
            }
        }

        return $result;
    }
}