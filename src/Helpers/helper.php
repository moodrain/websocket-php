<?php

use Muyu\WebSocket\Config;

function config($key, $default = null)
{
    return Config::get($key, $default);
}

function dd($dd)
{
    var_dump($dd);
    exit;
}