<?php
/**
 * SmtpMailer
 *
 * A small, self-contained SMTP client for PHP with no external dependencies.
 * Supports STARTTLS (e.g. Brevo on port 587) and AUTH LOGIN.
 *
 * It only needs the OpenSSL and sockets features that are enabled by default
 * on Hostinger shared hosting, so you can just upload and run it.
 */
class SmtpMailer
{
    private $host;
    private $port;
    private $user;
    private $pass;
    private $timeout;

    /** @var resource|null */
    private $socket = null;

    /** @var string Accumulated conversation log, useful for debugging. */
    public $log = '';

    public function __construct($host, $port, $user, $pass, $timeout = 30)
    {
        $this->host    = $host;
        $this->port    = (int) $port;
        $this->user    = $user;
        $this->pass    = $pass;
        $this->timeout = (int) $timeout;
    }

    /**
     * Send a plain-text email.
     *
     * @throws Exception on any SMTP error.
     */
    public function send($fromEmail, $fromName, $toEmail, $subject, $body)
    {
        $this->connect();

        try {
            $this->readResponse(220);

            $host = $this->clientHostname();
            $this->command("EHLO {$host}", 250);

            // Upgrade the plain connection to TLS.
            $this->command('STARTTLS', 220);
            $this->enableCrypto();

            // Must say EHLO again after the TLS handshake.
            $this->command("EHLO {$host}", 250);

            // Authenticate.
            $this->command('AUTH LOGIN', 334);
            $this->command(base64_encode($this->user), 334);
            $this->command(base64_encode($this->pass), 235);

            // Envelope.
            $this->command('MAIL FROM:<' . $fromEmail . '>', 250);
            $this->command('RCPT TO:<' . $toEmail . '>', 250);

            // Message.
            $this->command('DATA', 354);
            $this->sendData($this->buildMessage($fromEmail, $fromName, $toEmail, $subject, $body));
            $this->command('.', 250);

            $this->command('QUIT', 221);
        } finally {
            $this->close();
        }

        return true;
    }

    private function connect()
    {
        $errno  = 0;
        $errstr = '';
        // Plain TCP connection first; TLS is negotiated later via STARTTLS.
        $remote = 'tcp://' . $this->host . ':' . $this->port;

        $this->socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!$this->socket) {
            throw new Exception("Could not connect to {$this->host}:{$this->port} - [{$errno}] {$errstr}");
        }

        stream_set_timeout($this->socket, $this->timeout);
    }

    private function enableCrypto()
    {
        $ok = @stream_socket_enable_crypto(
            $this->socket,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
        );

        if ($ok !== true) {
            throw new Exception('Failed to start TLS encryption (STARTTLS).');
        }
    }

    /**
     * Send a command and verify the expected reply code.
     */
    private function command($cmd, $expectedCode)
    {
        $this->sendData($cmd);
        return $this->readResponse($expectedCode);
    }

    private function sendData($data)
    {
        $this->log .= 'C: ' . $data . "\n";
        fwrite($this->socket, $data . "\r\n");
    }

    /**
     * Read a (possibly multi-line) SMTP response and check the code.
     */
    private function readResponse($expectedCode)
    {
        $response = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            // Multi-line responses use "250-", the final line uses "250 ".
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $this->log .= 'S: ' . trim($response) . "\n";

        $code = (int) substr($response, 0, 3);
        if ($code !== (int) $expectedCode) {
            throw new Exception("SMTP error: expected {$expectedCode}, got: " . trim($response));
        }

        return $response;
    }

    private function buildMessage($fromEmail, $fromName, $toEmail, $subject, $body)
    {
        $fromName = $this->encodeHeader($fromName);
        $subject  = $this->encodeHeader($subject);

        $headers   = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        $headers[] = 'To: <' . $toEmail . '>';
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . $this->clientHostname() . '>';

        // Normalise line endings and dot-stuff lines starting with ".".
        $body = str_replace(["\r\n", "\r", "\n"], "\r\n", $body);
        $body = preg_replace('/^\./m', '..', $body);

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    private function encodeHeader($value)
    {
        // Encode non-ASCII so subjects/names with accents or emoji work.
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private function clientHostname()
    {
        if (!empty($_SERVER['SERVER_NAME'])) {
            return $_SERVER['SERVER_NAME'];
        }
        return gethostname() ?: 'localhost';
    }

    private function close()
    {
        if (is_resource($this->socket)) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}
