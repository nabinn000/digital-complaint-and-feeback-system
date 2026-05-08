<?php
namespace PHPMailer\PHPMailer;

/**
 * Minimal SMTP class compatible with PHPMailer 6.x API.
 */
class SMTP {
    const VERSION    = '6.9.1';
    const CRLF       = "\r\n";
    const DEFAULT_PORT        = 25;
    const DEFAULT_SMTP_PORT   = 465;
    const MAX_LINE_LENGTH     = 998;

    public $do_debug  = 0;
    public $Debugoutput = 'echo';
    protected $smtp_conn;
    protected $error     = ['error' => '', 'detail' => '', 'smtp_code' => '', 'smtp_code_ex' => ''];
    protected $helo_rply = null;
    protected $server_caps = null;
    protected $last_reply  = '';

    public function connect($host, $port = null, $timeout = 30, $options = []) {
        if (is_null($port)) $port = self::DEFAULT_PORT;
        $this->smtp_conn = @stream_socket_client(
            "$host:$port", $errno, $errstr, $timeout,
            STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => $options])
        );
        if (!$this->smtp_conn) {
            $this->setError("Failed to connect: $errstr ($errno)");
            return false;
        }
        stream_set_timeout($this->smtp_conn, $timeout, 0);
        $this->last_reply = $this->get_lines();
        return (int)substr($this->last_reply, 0, 3) === 220;
    }

    public function startTLS() {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) return false;
        $crypto = stream_socket_enable_crypto($this->smtp_conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        return (bool)$crypto;
    }

    public function hello($host = '') {
        return $this->sendHello('EHLO', $host) || $this->sendHello('HELO', $host);
    }

    protected function sendHello($hello, $host) {
        $noerror = $this->sendCommand($hello, "$hello $host", 250);
        $this->helo_rply = $this->last_reply;
        if ($noerror) {
            $this->server_caps = [];
            foreach (preg_split('/\n/', $this->helo_rply) as $n => $s) {
                if ($n == 0) continue;
                $s = trim(substr($s, 4));
                $fields = explode(' ', $s);
                if ($fields) {
                    $name = strtoupper(array_shift($fields));
                    $this->server_caps[$name] = $fields;
                }
            }
        }
        return $noerror;
    }

    public function authenticate($username, $password, $authtype = 'LOGIN', $OAuth = null) {
        if (!$this->sendCommand('AUTH LOGIN', 'AUTH LOGIN', 334)) return false;
        if (!$this->sendCommand('Username', base64_encode($username), 334)) return false;
        if (!$this->sendCommand('Password', base64_encode($password), 235)) return false;
        return true;
    }

    public function mail($from) {
        return $this->sendCommand('MAIL FROM', "MAIL FROM:<$from>", 250);
    }

    public function recipient($address, $dsn = '') {
        return $this->sendCommand('RCPT TO', "RCPT TO:<$address>", [250, 251]);
    }

    public function data($msg_data) {
        if (!$this->sendCommand('DATA', 'DATA', 354)) return false;
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $msg_data));
        $field = substr($lines[0], 0, strpos($lines[0], ':'));
        $in_headers = !empty($field) && !strstr($field, ' ');
        foreach ($lines as $line) {
            if ($in_headers && empty(trim($line))) $in_headers = false;
            if (!$in_headers && strlen($line) > self::MAX_LINE_LENGTH) {
                $line = wordwrap($line, self::MAX_LINE_LENGTH, "\n", true);
            }
            if (str_starts_with($line, '.')) $line = '.' . $line;
            fwrite($this->smtp_conn, $line . self::CRLF);
        }
        return $this->sendCommand('DATA END', '.', 250);
    }

    public function quit($close_on_error = true) {
        $this->sendCommand('QUIT', 'QUIT', 221);
        $this->close();
        return true;
    }

    public function close() {
        if (is_resource($this->smtp_conn)) {
            fclose($this->smtp_conn);
            $this->smtp_conn = null;
        }
    }

    public function getServerExtList() { return $this->server_caps; }
    public function getServerExt($name) {
        if (!$this->server_caps) return null;
        $name = strtoupper($name);
        return $this->server_caps[$name] ?? null;
    }
    public function getLastReply() { return $this->last_reply; }
    public function getError() { return $this->error; }

    protected function sendCommand($command, $commandstring, $expect) {
        fwrite($this->smtp_conn, $commandstring . self::CRLF);
        $this->last_reply = $this->get_lines();
        $code = (int)substr($this->last_reply, 0, 3);
        if (is_array($expect)) return in_array($code, $expect);
        return $code === $expect;
    }

    protected function get_lines() {
        if (!is_resource($this->smtp_conn)) return '';
        $data = '';
        $endtime = time() + 300;
        while (is_resource($this->smtp_conn) && !feof($this->smtp_conn)) {
            $str = @fgets($this->smtp_conn, 515);
            if (!$str) break;
            $data .= $str;
            if (substr($str, 3, 1) === ' ') break;
            if (time() > $endtime) break;
        }
        return $data;
    }

    protected function setError($msg, $detail = '', $smtp_code = '', $smtp_code_ex = '') {
        $this->error = ['error' => $msg, 'detail' => $detail, 'smtp_code' => $smtp_code, 'smtp_code_ex' => $smtp_code_ex];
    }
}