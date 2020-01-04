<?php

namespace StripeSync;

class Logger
{

    public static function debug($message)
    {
        if (Config::get('debug')) {
            echo sprintf("[%s]   [DEBUG]: %s\n", date('m/d/Y h:i:s'), $message);
        }
    }

    public static function info($message)
    {
        echo sprintf("[%s]    [INFO]: %s\n", date('m/d/Y h:i:s'), $message);
    }

    public static function error($message)
    {
        echo sprintf("[%s] [!ERROR!]: %s\n", date('m/d/Y h:i:s'), $message);
        die();
    }

    public static function softError($message)
    {
        echo sprintf("[%s] [!ERROR!]: %s\n", date('m/d/Y h:i:s'), $message);
    }

}