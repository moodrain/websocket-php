<?php

use Muyu\WebSocket\Connection;
use Muyu\WebSocket\Server;

require __DIR__ . '/vendor/autoload.php';

$server = new Server();


$server->on('open', function(Connection $client) use ($server) {
    echo 'client connect, id: ' . $client->id() . ' total count: ' . count($server->clients()), PHP_EOL;
});

$server->on('close', function(Connection $client) use ($server) {
    echo 'client close  , id: ' . $client->id() . ' total count: ' . count($server->clients()), PHP_EOL;
});

$server->on('message', function(Connection $client, $message) use ($server) {
    $server->sendPacket($client, 'get ' . $message);
});

$server->on('send', function(Connection $client, $message) use ($server) {
    echo 'client id ' . $client->id() . ' send ' . $message, PHP_EOL;
});

$server->start();
