<?php

namespace Muyu\WebSocket;

class Message
{
    private $type = Packet::MSG_TYPE_TXT;
    private $length = 0;
    private $content = '';
    private $packets = [];
    /* @var Connection **/
    private $connection;
    private $finish = false;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function addPacket(Packet $packet)
    {
        if ($this->finish) {
            return;
        }
        if ($this->length == 0) {
            $this->type = $packet->msgType();
        }
        $this->packets[] = $packet;
        $piece = $packet->payloadStr();
        $this->content .= $piece;
        $this->length += strlen($piece);
        $this->finish = $packet->isFinish();
    }

    public function isFinish()
    {
        return $this->finish;
    }

    public function type()
    {
        return $this->type;
    }

    public function length()
    {
        return $this->length;
    }

    public function content()
    {
        return $this->content;
    }

    public function connection()
    {
        return $this->connection;
    }

    public function packets()
    {
        return $this->packets;
    }

}