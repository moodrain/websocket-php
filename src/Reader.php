<?php

namespace Muyu\WebSocket;

class Reader
{
    private $type = self::TYPE_CONN;
    /* @var Connection **/
    private $conn;
    /* @var string **/
    private $file;
    private $fileReadIndex = 0;

    const TYPE_CONN = 1;
    const TYPE_FILE = 2;

    public function __construct()
    {
        $this->conn = new Connection(0, null);
        $this->file = '';
    }

    public function setConn(Connection $conn)
    {
        $this->type = self::TYPE_CONN;
        $this->conn = $conn;
    }

    public function setFile($file)
    {
        $this->type = self::TYPE_FILE;
        $this->file = $file;
    }

    public function type()
    {
        return $this->type;
    }

    public function read($size)
    {
        if ($this->type == self::TYPE_CONN) {
            $read = @socket_read($this->conn->conn(), $size);
            if (! $read) {
                $errCode = socket_last_error($this->conn->conn());
                if ($errCode === 10035) {
                    return null;
                } else {
                    $this->conn->server()->close($this->conn);
                    return false;
                }
            }
            return $read;
        } elseif ($this->type == self::TYPE_FILE) {
            $content = file_get_contents($this->file);
            if ($this->fileReadIndex >= strlen($content)) {
                return null;
            }
            $return = substr($content, $this->fileReadIndex, $size);
            $this->fileReadIndex += $size;
            return $return;
        } else {
            return false;
        }
    }

    public function conn()
    {
        return $this->conn;
    }

    public function file()
    {
        return $this->file;
    }

}