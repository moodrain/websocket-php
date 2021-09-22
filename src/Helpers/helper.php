<?php

use Muyu\WebSocket\Config;

function config($key, $default = null)
{
    return Config::get($key, $default);
}