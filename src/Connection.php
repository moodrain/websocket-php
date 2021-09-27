<?php

namespace Muyu\WebSocket;

class Connection
{
    private $id;
    private $conn;
    private $handshake = false;
    private $createdAt;
    private $activeAt;

    public function __construct($id)
    {
        $this->id = $id;
        $this->createdAt = time();
        $this->activeAt = time();
    }

    public function id()
    {
        return $this->id;
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