<?php

namespace Muyu\WebSocket;

class Server
{
    private $connection;
    private $clients = [];
    private $idAutoIncrement = 1;
    private $sendPacketLenLimit = 255;

    private $onOpen;
    private $onMessage;
    private $onSend;
    private $onClose;


    public function __construct()
    {
        $this->onOpen = function($client) {return true;};
        $this->onMessage = function($client, $message) {return true;};
        $this->onSend = function($client, $message) {return true;};
        $this->onClose = function($client) {return true;};
    }

    public function sendPacketLenLimit()
    {
        return $this->sendPacketLenLimit;
    }

    public function setSendPacketLenLimit($limit)
    {
        $this->sendPacketLenLimit = $limit;
    }

    public function setIdAutoIncrement($id) {
        $this->idAutoIncrement = $id;
    }

    public function on($event, $callback)
    {
        if (! in_array($event, ['open', 'message', 'send', 'close'])) {
            return;
        }
        if (! is_callable($callback)) {
            return;
        }
        $prop = 'on' . ucfirst($event);
        $this->$prop = $callback;
    }

    public function start()
    {
        $address = config('address', '127.0.0.1');
        $port =  config('port', 8001);
        $conn = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($conn, $address, $port);
        socket_listen($conn);
        socket_set_nonblock($conn);
        $this->connection = $conn;
        $this->waitNewClient();
    }

    public function startReadFile($file)
    {
        $reader = new Reader();
        $reader->setFile($file);
        $conn = new Connection(0);
        $msg = new Message($conn);
        while(true) {
            $packet = Packet::from($reader);
            $msg->addPacket($packet);
            if (! $packet || $packet->isFinish()) {
                break;
            }
        }
        $callback = $this->onMessage;
        $callback($conn, $msg);
    }

    private function waitNewClient()
    {
        while(true) {
            $client = socket_accept($this->connection);
            if ($client) {
                $conn = new Connection($this->idAutoIncrement);
                $conn->setConn($client);
                $this->clients[] = $conn;
                $this->idAutoIncrement++;
            }
            foreach ($this->clients as $client) {
                /* @var $client Connection **/
                if (! $client->handshake()) {
                    $this->wsHandshake($client);
                    if (time() - $client->createdAt() > config('close_un_handshake_conn_second', 30)) {
                        $this->close($client);
                    }
                } else {
                    if (time() - $client->activeAt() > config('close_inactive_conn_second', 300)) {
                        $this->close($client);
                    }
                    $packet = $this->readPacket($client);
                    if (! $packet) {
                        continue;
                    }
                    $msg = new Message($client);
                    $msg->addPacket($packet);
                    $type = $packet->msgType();
                    while ($packet && ! $packet->isFinish()) {
                        $packet = $this->readPacket($client);
                        $msg->addPacket($packet);
                    }
                    switch ($type) {
                        case Packet::MSG_TYPE_CLOSE:
                            $this->close($client);
                            break;
                        case Packet::MSG_TYPE_TXT:
                            $callback = $this->onMessage;
                            $callback($client, $msg);
                            break;
                    }
                }
            }
        }
    }

    private function wsHandshake(Connection $client)
    {
        $raw = $this->readHttp($client);
        if (! $raw) {
            return;
        }
        $callback = $this->onOpen;
        if ($callback($client) === false) {
            return;
        }
        $this->send($client, $this->handleHandshakePacket($raw));
        $client->finishHandshake();
    }

    private function handleHandshakePacket($packet)
    {
        $headers = [];
        $packet = explode("\r\n", $packet);
        foreach($packet as $line) {
            $line = trim($line);
            if (! $line || strpos($line, ':') === false) {
                continue;
            }
            [$key, $val] = explode(':', $line);
            $headers[trim($key)] = trim($val);
        }
        $GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';
        $rsAccept = base64_encode(sha1($headers['Sec-WebSocket-Key'] . $GUID, true));
        $rs = "HTTP/1.1 101 Switching Protocol\r\n";
        $rs .= "Upgrade: websocket\r\n";
        $rs .= "Connection: upgrade\r\n";
        $rs .= "Sec-WebSocket-Accept: " . $rsAccept . "\r\n";
        $rs .= "Sec-WebSocket-Version: 13\r\n\r\n";
        return $rs;
    }

    private function readPacket(Connection $client)
    {
        $reader = new Reader();
        $reader->setConn($client);
        return Packet::from($reader);
    }

    private function readHttp(Connection $client)
    {
        return socket_read($client->conn(), 4096);
    }

    public function send(Connection $client, $data)
    {
        socket_write($client->conn(), $data, strlen($data));
    }

    public function sendMessage(Connection $client, $type, $data)
    {
        $msg = new Message($client);
        $leftLen = strlen($data);
        $limit = $this->sendPacketLenLimit;
        $start = 0;
        while ($leftLen > 0) {
            $packet = Packet::create($start == 0 ? $type : Packet::MSG_TYPE_PIECE, substr($data, $start, $limit), $limit >= $leftLen);
            $msg->addPacket($packet);
            $start += $limit;
            $leftLen -= $limit;
        }
        $callback = $this->onSend;
        if ($callback($client, $msg) === false) {
            return;
        }
        foreach ($msg->packets() as $packet) {
            $this->send($client, $packet->rawBin());
        }
    }

    public function close(Connection $client)
    {
        $index = array_search($client, $this->clients);
        array_splice($this->clients, $index, 1);
        $this->clients = array_values($this->clients);
        $callback = $this->onClose;
        $callback($client);
    }

    public function clients()
    {
        return $this->clients;
    }
}