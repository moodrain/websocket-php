<?php

namespace Muyu\WebSocket;

class Config
{
    static $config;

    public static function get($key, $default = null)
    {
        if (is_null(self::$config)) {
            self::$config = require(__DIR__ . '/../config.php');
        }
        $value = self::$config[$key] ?? null;
        if (is_null($value) && ! is_null($default)) {
            return $default;
        }
        return $value;
    }

    public static function set($key, $value)
    {
        if (is_null(self::$config)) {
            self::$config = require(__DIR__ . '/../config.php');
        }
        self::$config[$key] = $value;
    }

}
