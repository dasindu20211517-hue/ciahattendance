<?php

class ZKTeco
{
    private $ip;
    private $port;
    private $timeout;
    private $password;
    private $sock;
    private $sessionId = 0;
    private $replyId = 0;

    const CMD_CONNECT       = 1000;
    const CMD_EXIT          = 1001;
    const CMD_ENABLEDEVICE  = 1002;
    const CMD_DISABLEDEVICE = 1003;
    const CMD_AUTH          = 1102;
    const CMD_PREPARE_DATA  = 1500;
    const CMD_DATA          = 1501;
    const CMD_FREE_DATA     = 1502;
    const CMD_ATTLOG_RRQ    = 13;
    const CMD_USERTEMP_RRQ  = 9;
    const CMD_VERSION       = 1100;
    const CMD_GET_TIME      = 201;
    const CMD_GET_PINWIDTH  = 69;
    const CMD_ACK_OK        = 2000;
    const CMD_ACK_ERROR     = 2001;
    const CMD_ACK_UNAUTH    = 2005;

    public function __construct($ip, $port = 4370, $timeout = 5, $password = 0)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->timeout = $timeout;
        $this->password = $password;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    private function createHeader($command, $chksum = 0, $sessionId = null, $replyId = null)
    {
        if ($sessionId === null) $sessionId = $this->sessionId;
        if ($replyId === null) $replyId = $this->replyId;
        return pack('SSSS', $command, $chksum, $sessionId, $replyId);
    }

    private function calculateChecksum($packet)
    {
        $data = unpack('v*', $packet);
        $sum = 0;
        foreach ($data as $word) {
            $sum += $word;
            if ($sum > 65535) {
                $sum -= 65535;
            }
        }
        $sum = (~$sum) & 0xFFFF;
        return $sum;
    }

    private function makeCommKey($key, $session)
    {
        $k = 0;
        for ($i = 0; $i < 5; $i++) {
            $k += unpack('V', substr(pack('V', $key), 0, 4))[1];
        }
        $k = $k % 0xFFFFFFFF;
        $k = $k ^ (hexdec('18372') * $session);
        return $k & 0xFFFF;
    }

    private function sendCommand($command, $payload = '', $sessionId = null, $replyId = null)
    {
        $header = $this->createHeader($command, 0, $sessionId, $replyId);
        $packet = $header . $payload;
        $chksum = $this->calculateChecksum($packet);
        $header = $this->createHeader($command, $chksum, $sessionId, $replyId);
        $packet = $header . $payload;
        socket_sendto($this->sock, $packet, strlen($packet), 0, $this->ip, $this->port);
        return $packet;
    }

    private function receiveData(&$buffer, &$address)
    {
        $buffer = '';
        $port = 0;
        $bytes = @socket_recvfrom($this->sock, $buffer, 1032, 0, $address, $port);
        return $bytes;
    }

    public function connect()
    {
        $this->sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        socket_set_option($this->sock, SOL_SOCKET, SO_RCVTIMEO, ['sec' => $this->timeout, 'usec' => 0]);
        socket_set_option($this->sock, SOL_SOCKET, SO_SNDTIMEO, ['sec' => $this->timeout, 'usec' => 0]);

        $this->replyId = 0;
        $this->sendCommand(self::CMD_CONNECT);
        $buf = '';
        $address = '';
        if ($this->receiveData($buf, $address) === false) {
            return false;
        }
        $this->sessionId = unpack('S', substr($buf, 4, 2))[1];
        if ($this->sessionId == 0) {
            $this->sessionId = 1;
        }

        $this->replyId++;
        $commKey = $this->makeCommKey($this->password, $this->sessionId);
        $payload = pack('V', $commKey);
        $this->sendCommand(self::CMD_AUTH, $payload);
        $buf = '';
        if ($this->receiveData($buf, $address) === false) {
            return false;
        }
        $command = unpack('S', substr($buf, 0, 2))[1];
        if ($command == self::CMD_ACK_ERROR) {
            return false;
        }

        return true;
    }

    public function disconnect()
    {
        if ($this->sock !== null) {
            $this->replyId++;
            $this->sendCommand(self::CMD_EXIT);
            socket_close($this->sock);
            $this->sock = null;
        }
    }

    public function enableDevice()
    {
        $this->replyId++;
        $this->sendCommand(self::CMD_ENABLEDEVICE);
        $buf = '';
        $address = '';
        $this->receiveData($buf, $address);
    }

    public function disableDevice()
    {
        $this->replyId++;
        $this->sendCommand(self::CMD_DISABLEDEVICE);
        $buf = '';
        $address = '';
        $this->receiveData($buf, $address);
    }

    public function version()
    {
        $this->replyId++;
        $this->sendCommand(self::CMD_VERSION);
        $buf = '';
        $address = '';
        if ($this->receiveData($buf, $address) === false) {
            return '';
        }
        return trim(substr($buf, 8), "\0");
    }

    public function getTime()
    {
        $this->replyId++;
        $this->sendCommand(self::CMD_GET_TIME);
        $buf = '';
        $address = '';
        if ($this->receiveData($buf, $address) === false) {
            return false;
        }
        $data = unpack('V', substr($buf, 8, 4));
        if (!$data) return false;
        return $this->decodeTime($data[1]);
    }

    private function decodeTime($timeValue)
    {
        if ($timeValue === 0 || $timeValue === false) return null;
        $second = $timeValue % 60;
        $timeValue = intdiv($timeValue, 60);
        $minute = $timeValue % 60;
        $timeValue = intdiv($timeValue, 60);
        $hour = $timeValue % 24;
        $timeValue = intdiv($timeValue, 24);
        $day = $timeValue % 31 + 1;
        $timeValue = intdiv($timeValue, 31);
        $month = $timeValue % 12 + 1;
        $timeValue = intdiv($timeValue, 12);
        $year = ($timeValue % 100) + 2000;
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second);
    }

    public function getUsers()
    {
        $this->replyId++;
        $this->sendCommand(self::CMD_USERTEMP_RRQ);
        $buf = '';
        $address = '';
        if ($this->receiveData($buf, $address) === false) {
            $this->replyId++;
            $this->sendCommand(self::CMD_USERTEMP_RRQ, chr(5));
            $buf = '';
            $address = '';
            if ($this->receiveData($buf, $address) === false) {
                return [];
            }
        }
        $command = unpack('S', substr($buf, 0, 2))[1];

        if ($command == self::CMD_PREPARE_DATA) {
            $size = unpack('V', substr($buf, 8, 4))[1];
            $users = [];
            $received = 0;
            while ($received < $size) {
                $buf = '';
                if ($this->receiveData($buf, $address) === false) break;
                $cmd = unpack('S', substr($buf, 0, 2))[1];
                if ($cmd == self::CMD_DATA) {
                    $data = substr($buf, 8);
                    $received += strlen($data);
                    $users = array_merge($users, $this->parseUsers($data));
                }
            }
            return $users;
        }

        if ($command == self::CMD_DATA) {
            $users = $this->parseUsers(substr($buf, 8));
            while (true) {
                $buf = '';
                if ($this->receiveData($buf, $address) === false) break;
                $cmd = unpack('S', substr($buf, 0, 2))[1];
                if ($cmd == self::CMD_DATA) {
                    $users = array_merge($users, $this->parseUsers(substr($buf, 8)));
                } else {
                    break;
                }
            }
            return $users;
        }

        return [];
    }

    private function parseUsers($data)
    {
        $users = [];
        $offset = 0;
        while ($offset + 72 <= strlen($data)) {
            $uid = unpack('v', substr($data, $offset, 2))[1];
            $role = unpack('v', substr($data, $offset + 2, 2))[1];
            $password = trim(substr($data, $offset + 4, 8), "\0");
            $name = trim(substr($data, $offset + 12, 28), "\0");
            $badge = trim(substr($data, $offset + 49, 9), "\0");
            $card = unpack('V', substr($data, $offset + 57, 4))[1];
            $users[$uid] = [
                'uid' => $uid,
                'id' => $badge,
                'name' => $name,
                'password' => $password,
                'role' => $role,
                'card' => $card,
            ];
            $offset += 72;
        }
        return $users;
    }

    public function getAttendances()
    {
        $this->replyId++;
        $this->sendCommand(self::CMD_ATTLOG_RRQ);
        $buf = '';
        $address = '';
        $gotData = false;
        if ($this->receiveData($buf, $address) !== false) {
            $command = unpack('S', substr($buf, 0, 2))[1];
            if ($command == self::CMD_PREPARE_DATA || $command == self::CMD_DATA) {
                $gotData = true;
            }
        }

        if (!$gotData) {
            $this->replyId++;
            $this->sendCommand(self::CMD_ATTLOG_RRQ, chr(5));
            $buf = '';
            $address = '';
            if ($this->receiveData($buf, $address) === false) {
                return [];
            }
            $command = unpack('S', substr($buf, 0, 2))[1];
            if ($command != self::CMD_PREPARE_DATA && $command != self::CMD_DATA) {
                return [];
            }
        }

        if ($command == self::CMD_PREPARE_DATA) {
            $size = unpack('V', substr($buf, 8, 4))[1];
            error_log('ZK: PREPARE_DATA size=' . $size);
            $attendances = [];
            $received = 0;
            $pkts = 0;
            while ($received < $size) {
                $buf = '';
                if ($this->receiveData($buf, $address) === false) {
                    error_log('ZK: ATTLOG timeout after ' . $pkts . ' pkts, received=' . $received);
                    break;
                }
                $cmd = unpack('S', substr($buf, 0, 2))[1];
                if ($cmd == self::CMD_DATA) {
                    $data = substr($buf, 8);
                    $received += strlen($data);
                    $pkts++;
                    if ($pkts <= 2) {
                        error_log('ZK: ATTLOG pkt#' . $pkts . ' data_len=' . strlen($data) . ' hex=' . bin2hex(substr($data, 0, 40)));
                    }
                    $attendances = array_merge($attendances, $this->parseAttendances($data));
                } else {
                    error_log('ZK: ATTLOG unexpected CMD=' . $cmd . ' at pkt#' . $pkts);
                    break;
                }
            }
            error_log('ZK: ATTLOG total pkts=' . $pkts . ' records=' . count($attendances));
            return $attendances;
        }

        if ($command == self::CMD_DATA) {
            $data = substr($buf, 8);
            error_log('ZK: ATTLOG direct CMD_DATA data_len=' . strlen($data) . ' hex=' . bin2hex(substr($data, 0, 40)));
            $attendances = $this->parseAttendances($data);
            while (true) {
                $buf = '';
                if ($this->receiveData($buf, $address) === false) break;
                $cmd = unpack('S', substr($buf, 0, 2))[1];
                if ($cmd == self::CMD_DATA) {
                    $attendances = array_merge($attendances, $this->parseAttendances(substr($buf, 8)));
                } else {
                    break;
                }
            }
            return $attendances;
        }

        error_log('ZK: ATTLOG unexpected response CMD=' . $command);
        return [];
    }

    private function parseAttendances($data)
    {
        $attendances = [];
        $offset = 0;
        while ($offset + 40 <= strlen($data)) {
            $uid = unpack('v', substr($data, $offset, 2))[1];
            $id = trim(substr($data, $offset + 2, 10), "\0");

            $timeVal = unpack('V', substr($data, $offset + 29, 4))[1];
            $time = $this->decodeTime($timeVal);

            $state = unpack('C', substr($data, $offset + 28, 1))[1];
            if ($state > 20) {
                $altState = unpack('C', substr($data, $offset + 32, 1))[1];
                if ($altState <= 5) {
                    $state = $altState;
                }
            }
            $state = $state & 0x07;

            $attendances[] = [
                'uid' => $uid,
                'id' => $id,
                'state' => $state,
                'time' => $time,
            ];
            $offset += 40;
        }
        return $attendances;
    }
}
