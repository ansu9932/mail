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
     * Send an email.
     *
     * @param string $body   The message body.
     * @param bool   $isHtml If true, $body is treated as HTML and a
     *                       multipart/alternative message (HTML + plain-text
     *                       fallback) is sent. If false, a plain-text email
     *                       is sent.
     *
     * @throws Exception on any SMTP error.
     */
    public function send($fromEmail, $fromName, $toEmail, $subject, $body, $isHtml = false)
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
            $this->sendData($this->buildMessage($fromEmail, $fromName, $toEmail, $subject, $body, $isHtml));
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

    private function buildMessage($fromEmail, $fromName, $toEmail, $subject, $body, $isHtml = false)
    {
        $fromName = $this->encodeHeader($fromName);
        $subject  = $this->encodeHeader($subject);

        $headers   = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
        $headers[] = 'To: <' . $toEmail . '>';
        $headers[] = 'Subject: ' . $subject;
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(8)) . '@' . $this->clientHostname() . '>';

        if ($isHtml) {
            // Send both a plain-text and an HTML version so that every email
            // client shows something sensible. Bold/formatting is preserved
            // by the HTML part.
            $boundary = 'bnd_' . bin2hex(random_bytes(12));

            $plain = $this->htmlToPlainText($body);
            $html  = $this->wrapHtml($body);

            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

            $parts   = [];
            $parts[] = '--' . $boundary;
            $parts[] = 'Content-Type: text/plain; charset=UTF-8';
            $parts[] = 'Content-Transfer-Encoding: 8bit';
            $parts[] = '';
            $parts[] = $this->prepareBody($plain);
            $parts[] = '--' . $boundary;
            $parts[] = 'Content-Type: text/html; charset=UTF-8';
            $parts[] = 'Content-Transfer-Encoding: 8bit';
            $parts[] = '';
            $parts[] = $this->prepareBody($html);
            $parts[] = '--' . $boundary . '--';

            $bodyOut = implode("\r\n", $parts);
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            $bodyOut   = $this->prepareBody($body);
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $bodyOut;
    }

    /**
     * Normalise line endings to CRLF and dot-stuff lines starting with ".".
     */
    private function prepareBody($body)
    {
        $body = str_replace(["\r\n", "\r", "\n"], "\r\n", $body);
        $body = preg_replace('/^\./m', '..', $body);
        return $body;
    }

    /**
     * Wrap the user's HTML fragment in a minimal, email-friendly document.
     * The reset styles remove the large gaps some clients add between blocks.
     */
    private function wrapHtml($html)
    {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<style>'
            . 'body{margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;'
            . 'font-size:15px;line-height:1.5;color:#1a202c;}'
            . 'p{margin:0 0 10px;} ul,ol{margin:0 0 10px 20px;padding:0;}'
            . 'img{max-width:100%;height:auto;}'
            . '</style></head><body>' . $html . '</body></html>';
    }

    /**
     * Build a readable plain-text fallback from an HTML fragment so line
     * spacing stays compact instead of showing raw markup or huge gaps.
     */
    private function htmlToPlainText($html)
    {
        $text = $html;
        // Turn block-level breaks into single newlines.
        $text = preg_replace('#<\s*br\s*/?\s*>#i', "\n", $text);
        $text = preg_replace('#</\s*(p|div|li|tr|h[1-6])\s*>#i', "\n", $text);
        $text = preg_replace('#<\s*li[^>]*>#i', '- ', $text);
        // Drop all remaining tags.
        $text = strip_tags($text);
        // Decode entities like &nbsp; &amp; etc.
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Collapse excessive blank lines and trailing spaces.
        $text = preg_replace('/[ \t]+\n/', "\n", $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);
        return trim($text);
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
