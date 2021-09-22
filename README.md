#### WebSocket-PHP

```
$server = new Server();

$server->on('open', function(Connection $client) use ($server) {
    echo 'client connect, id: ' . $client->id() . ' total count: ' . count($server->clients()), PHP_EOL;
});

$server->on('close', function(Connection $client) use ($server) {
    echo 'client close  , id: ' . $client->id() . ' total count: ' . count($server->clients()), PHP_EOL;
});

$server->on('message', function(Connection $client, Packet $packet) use ($server) {
    $server->sendPacket($client, 'get ' . $packet->payloadStr());
});

$server->on('send', function(Connection $client, Packet $packet) use ($server) {
    echo 'client id ' . $client->id() . ' send ' . $packet->payloadStr(), PHP_EOL;
});

$server->start();
```