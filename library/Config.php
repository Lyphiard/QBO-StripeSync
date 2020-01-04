<?php

namespace StripeSync;

class Config
{

    private static $_configuration;

    public static function get($path)
    {
        self::loadConfiguration();

        $path = explode('/', $path);
        $config = self::$_configuration;

        foreach ($path as $key) {
            $config = @$config[$key];
        }

        return $config;
    }

    private static function loadConfiguration()
    {
        if (self::$_configuration == null) {
            self::$_configuration = require __DIR__ . '/../config.php';
        }
    }

}