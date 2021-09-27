#### WebSocket-PHP

```
$server = new Server();

$server->on('open', function(Connection $client) use ($server) {
    echo 'client connect, id: ' . $client->id() . ' total count: ' . count($server->clients()), PHP_EOL;
});

$server->on('close', function(Connection $client) use ($server) {
    echo 'client close  , id: ' . $client->id() . ' total count: ' . count($server->clients()), PHP_EOL;
});

$server->on('message', function(Connection $client, Message $message) use ($server) {
    $server->sendMessage($client, \Muyu\WebSocket\Packet::MSG_TYPE_TXT, 'get ' . $message->content());
});

$server->on('send', function(Connection $client, Message $message) use ($server) {
    echo 'client id ' . $client->id() . ' send ' . $message->content(), PHP_EOL;
});

$server->start();
```