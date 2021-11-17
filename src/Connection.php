<?php

namespace Muyu\WebSocket;

class Connection
{
    private $id;
    private $conn;
    private $server;
    private $handshake = false;
    private $createdAt;
    private $activeAt;

    public function __construct($id, Server $server = null)
    {
        $this->id = $id;
        $this->server = $server;
        $this->createdAt = time();
        $this->activeAt = time();
    }

    public function id()
    {
        return $this->id;
    }

    public function server()
    {
        return $this->server;
    }

    public function createdAt()
    {
        return $this->createdAt;
    }

    public function activeAt()
    {
        return $this->activeAt;
    }

    public function active()
    {
        $this->activeAt = time();
    }

    public function conn()
    {
        return $this->conn;
    }

    public function setConn($conn)
    {
        $this->conn = $conn;
    }

    public function handshake()
    {
        return $this->handshake;
    }

    public function finishHandshake()
    {
        $this->handshake = true;
    }
}