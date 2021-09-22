<?php

namespace Muyu\WebSocket;


class Packet
{
    /** @var Connection */
    private $conn;
    private $rawBin;
    private $rawBinArr;
    private $isFinish;
    private $msgType;
    private $isMasked;
    private $maskIndex;
    private $maskBinArr;
    private $payloadLen;
    private $payloadLenExt;
    private $rawPayloadBinArr;
    private $payloadBinArr;
    private $payloadStr;

    const MSG_TYPE_PIECE = 0;
    const MSG_TYPE_TXT = 1;
    const MSG_TYPE_BIN = 2;
    const MSG_TYPE_CLOSE = 8;
    const MSG_TYPE_PING = 9;
    const MSG_TYPE_PONG = 10;

    public static function from(Connection $conn)
    {
        $packet = new Packet();
        $packet->conn = $conn;
        $packet->readFromConn();
        if ($packet->rawBin) {
            return $packet;
        }
        return null;
    }

    public static function createTextPacket($text)
    {
        $packet = new Packet();
        $packet->addBinArr(1,0,0,0);
        $packet->addBinArr(0,0,0,1);
        $len = strlen($text);
        if ($len <= 125) {
            $packet->addBinArr(...str_split(str_pad(decbin($len), 8, '0', STR_PAD_LEFT)));
        } elseif ($len > 125 && $len <= 255) {
            $packet->addBinArr(0,1,1,1,1,1,1,0);
            $packet->addBinArr(...str_split(str_pad(decbin($len), 16, '0', STR_PAD_LEFT)));
        } else {
            $packet->addBinArr(0,1,1,1,1,1,1,1);
            $packet->addBinArr(...str_split(str_pad(decbin($len), 64, '0', STR_PAD_LEFT)));
        }
        for ($i = 0; $i < $len; $i++) {
            $ord = ord($text[$i]);
            $binArr = str_split(str_pad(decbin($ord), 8, '0', STR_PAD_LEFT));
            $packet->addBinArr(...$binArr);
        }
        $bin = '';
        $count = count($packet->rawBinArr);
        for ($i = 0; $i < $count;$i += 8) {
            $code = 0;
            for ($j = 0; $j < 8; $j++) {
                $code += $packet->rawBinArr[$i + $j] * pow(2, 8 - 1 - $j);
            }
            $bin .= hex2bin(str_pad(dechex($code), 2, '0', STR_PAD_LEFT));
        }
        $packet->rawBin = $bin;
        $packet->getPayloadLength(false);
        $packet->getMaskIndex();
        $packet->getMaskBinArr();
        $packet->getPayloadBinArr();
        $packet->payloadStr = $text;
        return $packet;
    }

    private function addBinArr(...$add)
    {
        foreach ($add as $a) {
            $this->rawBinArr[] = $a;
        }
    }

    public function rawBin()
    {
        return $this->rawBin;
    }

    public function rawBinArr()
    {
        return $this->rawBinArr;
    }

    public function isFinish()
    {
        return $this->isFinish;
    }

    public function msgType()
    {
        return $this->msgType;
    }

    public function payloadLen()
    {
        return $this->payloadLen;
    }

    public function rawPayloadBinArr()
    {
        return $this->rawPayloadBinArr;
    }

    public function payloadBinArr()
    {
        return $this->payloadBinArr;
    }

    public function payloadStr()
    {
        return $this->payloadStr;
    }

    private function readFromConn()
    {
        if (! $this->readHead()) {
            return;
        }
        $this->conn->active();
        $this->readBody();
    }

    private function readHead()
    {
        $read = socket_read($this->conn->conn(), 2);
        if (! $read) {
            return false;
        }
        $this->rawBin = $read;
        $this->bin2Arr();
        $this->isFinish = $this->rawBinArr[0] == 1;
        $this->msgType = 0;
        for ($i = 4; $i < 8; $i++) {
            $this->msgType += $this->rawBinArr[$i] * pow(2, 8 - 1 - $i);
        }
        $this->isMasked = $this->rawBinArr[8] == 1;
        $this->getPayloadLength();
        $this->getMaskIndex();
        if ($this->isMasked) {
            $headLen = ($this->maskIndex / 8) + 4;
        }  else {
            $headLen = 2 + $this->payloadLenExt / 8;
        }
        if ($headLen - 2 > 0) {
            $read = socket_read($this->conn->conn(), $headLen - 2);
            $this->rawBin .= $read;
            $this->bin2Arr();
            $this->getMaskBinArr();
        }
        return true;
    }

    private function readBody()
    {
        $read = socket_read($this->conn->conn(), $this->payloadLen / 8);
        if (! $read) {
            return;
        }
        $this->rawBin .= $read;
        $this->bin2Arr();
        $this->getPayloadBinArr();
        $this->getPayloadStr();
    }

    private function bin2Arr()
    {
        $hex = bin2hex($this->rawBin);
        $binArr = [];
        for ($i = 0; $i < strlen($hex); $i++) {
            $bin = decbin(intval(substr($hex, $i, 1), 16));
            for ($j = 0; $j < 4 - strlen($bin); $j++) {
                $binArr[] = 0;
            }
            for ($k = 0; $k < strlen($bin); $k++) {
                $binArr[] = (int) substr($bin, $k, 1);
            }
        }
        $this->rawBinArr = $binArr;
    }

    private function getMaskIndex()
    {
        if (! $this->isMasked) {
            return;
        }
        $this->maskIndex = 16 + $this->payloadLenExt;
    }

    private function getMaskBinArr()
    {
        if (! $this->maskIndex) {
            return;
        }
        $this->maskBinArr = array_slice($this->rawBinArr, $this->maskIndex, 32);
    }

    private function getPayloadLength($readExtFromConn = true)
    {
        $lengthBinStr = join(array_slice($this->rawBinArr, 9, 7));
        $length = 0;
        if ($lengthBinStr == '1111110') {
            $this->payloadLenExt = 16;
            if ($readExtFromConn) {
                $read = socket_read($this->conn->conn(), 2);
                if (! $read) {
                    return false;
                }
                $this->rawBin .= $read;
                $this->bin2Arr();
            }
            for ($i = 16; $i < 32;$i++) {
                $length += $this->rawBinArr[$i] * pow(2, 32 - 1 - $i);
            }
        } elseif ($lengthBinStr == '1111111') {
            $this->payloadLenExt = 64;
            if ($readExtFromConn) {
                $read = socket_read($this->conn->conn(), 8);
                if (! $read) {
                    return false;
                }
                $this->rawBin .= $read;
                $this->bin2Arr();
            }
            for ($i = 16; $i < 80;$i++) {
                $length += $this->rawBinArr[$i] * pow(2, 80 - 1 - $i);
            }
        } else {
            $this->payloadLenExt = 0;
            for ($i = 9; $i < 16;$i++) {
                $length += $this->rawBinArr[$i] * pow(2, 16 - 1 - $i);
            }
        }
        $this->payloadLen = $length * 8;
    }

    private function getPayloadBinArr()
    {
        $start = $this->isMasked ? ($this->maskIndex + 32) : 16;
        $this->rawPayloadBinArr = $raw = array_slice($this->rawBinArr, $start, $this->payloadLen);
        if (! $this->isMasked) {
            return;
        }
        $count = count($raw);
        $decode = [];
        for ($i = 0; $i < $count; $i++) {
            $decode[$i] = $raw[$i] ^ ($this->maskBinArr[$i % 32]);
        }
        $this->payloadBinArr = $decode;
    }

    private function getPayloadStr()
    {
        $str = '';
        $count = count($this->payloadBinArr);
        for ($i = 0; $i < $count;$i += 8) {
            $code = 0;
            for ($j = 0; $j < 8; $j++) {
                $code += $this->payloadBinArr[$i + $j] * pow(2, 8 - 1 - $j);
            }
            $str .= chr($code);
        }
        $this->payloadStr = $str;
    }

    public function setConn(Connection $conn)
    {
        $this->conn = $conn;
    }

}