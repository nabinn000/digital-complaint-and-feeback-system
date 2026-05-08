<?php
namespace PHPMailer\PHPMailer;

class PHPMailer {
    const VERSION = '6.9.1';
    const CHARSET_UTF8 = 'utf-8';
    const CONTENT_TYPE_TEXT_HTML = 'text/html';

    public $isSMTP_Flag = false;
    public $Host        = 'smtp.gmail.com';
    public $SMTPAuth    = true;
    public $Username    = '';
    public $Password    = '';
    public $SMTPSecure  = 'tls';
    public $Port        = 587;
    public $SMTPDebug   = 0;
    public $Debugoutput = 'echo';
    public $From        = '';
    public $FromName    = '';
    public $Subject     = '';
    public $Body        = '';
    public $AltBody     = '';
    public $CharSet     = self::CHARSET_UTF8;
    public $ContentType = self::CONTENT_TYPE_TEXT_HTML;
    public $WordWrap    = 0;
    public $Timeout     = 300;
    public $ErrorInfo   = '';
    public $exceptions  = false;
    protected $to       = [];
    protected $cc       = [];
    protected $bcc      = [];
    protected $smtp     = null;

    public function __construct($exceptions = null) {
        if ($exceptions !== null) $this->exceptions = (bool)$exceptions;
    }

    public function isSMTP() { $this->isSMTP_Flag = true; }
    public function isHTML($isHtml = true) { $this->ContentType = $isHtml ? self::CONTENT_TYPE_TEXT_HTML : 'text/plain'; }

    public function addAddress($address, $name = '') {
        $this->to[] = [$address, $name];
        return true;
    }

    public function setFrom($address, $name = '', $auto = true) {
        $this->From     = $address;
        $this->FromName = $name;
        return true;
    }

    public function send() {
        try {
            if (empty($this->to)) throw new Exception('No recipients defined');
            $this->smtp = new SMTP();
            $this->smtp->do_debug = $this->SMTPDebug;

            // Connect
            $host    = $this->Host;
            $port    = $this->Port;
            $options = [];
            if ($this->SMTPSecure === 'ssl') {
                $host = 'ssl://' . $host;
                $options = ['verify_peer' => false, 'verify_peer_name' => false];
            }
            if (!$this->smtp->connect($host, $port, $this->Timeout, $options)) {
                throw new Exception('SMTP connect failed: ' . $this->smtp->getError()['error']);
            }

            // EHLO
            $hostname = gethostname() ?: 'localhost';
            if (!$this->smtp->hello($hostname)) {
                throw new Exception('EHLO failed');
            }

            // STARTTLS
            if ($this->SMTPSecure === 'tls') {
                if (!$this->smtp->startTLS()) throw new Exception('STARTTLS failed');
                if (!$this->smtp->hello($hostname)) throw new Exception('EHLO after TLS failed');
            }

            // Auth
            if ($this->SMTPAuth) {
                if (!$this->smtp->authenticate($this->Username, $this->Password)) {
                    throw new Exception('SMTP authentication failed');
                }
            }

            // Send
            if (!$this->smtp->mail($this->From)) throw new Exception('MAIL FROM failed');
            foreach ($this->to as [$addr]) {
                if (!$this->smtp->recipient($addr)) throw new Exception("RCPT TO <$addr> failed");
            }

            $msg = $this->buildMessage();
            if (!$this->smtp->data($msg)) throw new Exception('DATA command failed');

            $this->smtp->quit();
            return true;

        } catch (Exception $e) {
            $this->ErrorInfo = $e->getMessage();
            if ($this->smtp) $this->smtp->close();
            if ($this->exceptions) throw $e;
            return false;
        }
    }

    protected function buildMessage() {
        $to_str = implode(', ', array_map(fn($r) => $r[1] ? "\"{$r[1]}\" <{$r[0]}>" : $r[0], $this->to));
        $from   = $this->FromName ? "\"{$this->FromName}\" <{$this->From}>" : $this->From;
        $date   = date('r');
        $msgid  = '<' . uniqid('ccs_', true) . '@' . (gethostname() ?: 'localhost') . '>';
        $ctype  = $this->ContentType . '; charset=' . $this->CharSet;

        $headers  = "Date: $date\r\n";
        $headers .= "From: $from\r\n";
        $headers .= "To: $to_str\r\n";
        $headers .= "Subject: " . $this->encodeHeader($this->Subject) . "\r\n";
        $headers .= "Message-ID: $msgid\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: $ctype\r\n";
        $headers .= "Content-Transfer-Encoding: base64\r\n";
        $headers .= "X-Mailer: PHPMailer/CCS\r\n";

        $body = chunk_split(base64_encode($this->Body));
        return $headers . "\r\n" . $body;
    }

    protected function encodeHeader($str) {
        if (preg_match('/[^\x20-\x7E]/', $str)) {
            return '=?UTF-8?B?' . base64_encode($str) . '?=';
        }
        return $str;
    }

    public function clearAddresses()  { $this->to  = []; }
    public function clearCCs()        { $this->cc  = []; }
    public function clearBCCs()       { $this->bcc = []; }
    public function clearAllRecipients() { $this->to = $this->cc = $this->bcc = []; }
}