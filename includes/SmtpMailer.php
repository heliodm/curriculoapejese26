<?php
class SmtpMailer {
    private string $host;
    private int    $port;
    private string $encryption; // 'ssl', 'tls' (STARTTLS), 'none'
    private string $username;
    private string $password;
    private int    $timeout = 30;

    public function __construct(
        string $host,
        int    $port,
        string $encryption,
        string $username,
        string $password
    ) {
        $this->host       = $host;
        $this->port       = $port;
        $this->encryption = strtolower($encryption);
        $this->username   = $username;
        $this->password   = $password;
    }

    public function send(
        string $from,
        string $fromName,
        string $to,
        string $toName,
        string $subject,
        string $htmlBody,
        array  $attachments = [],
        string $replyTo = ''
    ): bool {
        $msgId     = '<' . bin2hex(random_bytes(16)) . '@smtp>';
        $date      = date('r');
        $fromEnc   = $this->encodeHeader($fromName) . ' <' . $from . '>';
        $toEnc     = $this->encodeHeader($toName) . ' <' . $to . '>';
        $subjectEnc = $this->encodeHeader($subject);
        $plainText  = $this->htmlToText($htmlBody);

        if (empty($attachments)) {
            $altBnd  = '----=_Alt_' . bin2hex(random_bytes(8));
            $body    = "--{$altBnd}\r\n"
                     . "Content-Type: text/plain; charset=UTF-8\r\n"
                     . "Content-Transfer-Encoding: base64\r\n\r\n"
                     . chunk_split(base64_encode($plainText))
                     . "--{$altBnd}\r\n"
                     . "Content-Type: text/html; charset=UTF-8\r\n"
                     . "Content-Transfer-Encoding: base64\r\n\r\n"
                     . chunk_split(base64_encode($htmlBody))
                     . "--{$altBnd}--";
            $ctHeader = "multipart/alternative; boundary=\"{$altBnd}\"";
        } else {
            $mixBnd  = '----=_Mix_' . bin2hex(random_bytes(8));
            $altBnd  = '----=_Alt_' . bin2hex(random_bytes(8));
            $altPart = "--{$altBnd}\r\n"
                     . "Content-Type: text/plain; charset=UTF-8\r\n"
                     . "Content-Transfer-Encoding: base64\r\n\r\n"
                     . chunk_split(base64_encode($plainText))
                     . "--{$altBnd}\r\n"
                     . "Content-Type: text/html; charset=UTF-8\r\n"
                     . "Content-Transfer-Encoding: base64\r\n\r\n"
                     . chunk_split(base64_encode($htmlBody))
                     . "--{$altBnd}--";
            $body    = "--{$mixBnd}\r\n"
                     . "Content-Type: multipart/alternative; boundary=\"{$altBnd}\"\r\n\r\n"
                     . $altPart . "\r\n";
            foreach ($attachments as $att) {
                if (empty($att['data']) || empty($att['name'])) continue;
                $safeName = preg_replace('/[^\w.\-]/', '_', $att['name']);
                $mimeType = $att['type'] ?? 'application/octet-stream';
                $body    .= "--{$mixBnd}\r\n"
                         . "Content-Type: {$mimeType}; name=\"{$safeName}\"\r\n"
                         . "Content-Transfer-Encoding: base64\r\n"
                         . "Content-Disposition: attachment; filename=\"{$safeName}\"\r\n\r\n"
                         . chunk_split(base64_encode($att['data'])) . "\r\n";
            }
            $body    .= "--{$mixBnd}--";
            $ctHeader = "multipart/mixed; boundary=\"{$mixBnd}\"";
        }

        $headers  = "Date: {$date}\r\n"
                  . "Message-ID: {$msgId}\r\n"
                  . "From: {$fromEnc}\r\n"
                  . "To: {$toEnc}\r\n"
                  . "Subject: {$subjectEnc}\r\n";
        if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers .= "Reply-To: {$replyTo}\r\n";
        }
        $headers .= "MIME-Version: 1.0\r\n"
                  . "Content-Type: {$ctHeader}\r\n"
                  . "X-Mailer: PHP/" . PHP_VERSION . "\r\n";

        return $this->deliver($from, $to, $headers . "\r\n" . $body);
    }

    private function deliver(string $from, string $to, string $message): bool {
        $address = ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host . ':' . $this->port;
        $ctx     = stream_context_create(['ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true,
        ]]);

        $conn = @stream_socket_client($address, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$conn) return false;

        stream_set_timeout($conn, $this->timeout);

        try {
            $this->expect($conn, 220);

            $ehlo = gethostname() ?: 'localhost';
            fwrite($conn, "EHLO {$ehlo}\r\n");
            $this->read($conn); // consume multi-line EHLO reply

            if ($this->encryption === 'tls') {
                fwrite($conn, "STARTTLS\r\n");
                $this->expect($conn, 220);
                if (!stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($conn);
                    return false;
                }
                fwrite($conn, "EHLO {$ehlo}\r\n");
                $this->read($conn);
            }

            if ($this->username !== '') {
                fwrite($conn, "AUTH LOGIN\r\n");
                $this->expect($conn, 334);
                fwrite($conn, base64_encode($this->username) . "\r\n");
                $this->expect($conn, 334);
                fwrite($conn, base64_encode($this->password) . "\r\n");
                $this->expect($conn, 235);
            }

            fwrite($conn, "MAIL FROM:<{$from}>\r\n");
            $this->expect($conn, 250);

            fwrite($conn, "RCPT TO:<{$to}>\r\n");
            $this->expectAny($conn, [250, 251]);

            fwrite($conn, "DATA\r\n");
            $this->expect($conn, 354);

            // RFC 5321 dot-stuffing
            $lines   = explode("\n", str_replace("\r\n", "\n", $message));
            $stuffed = implode("\r\n", array_map(
                static fn($l) => (isset($l[0]) && $l[0] === '.') ? '.' . $l : $l,
                $lines
            ));
            fwrite($conn, $stuffed . "\r\n.\r\n");
            $this->expect($conn, 250);

            fwrite($conn, "QUIT\r\n");
        } catch (\RuntimeException $e) {
            fclose($conn);
            return false;
        }

        fclose($conn);
        return true;
    }

    private function read($conn): string {
        $resp = '';
        while (($line = fgets($conn, 512)) !== false) {
            $resp .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') break;
        }
        return $resp;
    }

    private function expect($conn, int $code): void {
        $resp = $this->read($conn);
        if ((int) substr($resp, 0, 3) !== $code) {
            throw new \RuntimeException("SMTP expected {$code}, got: " . trim($resp));
        }
    }

    private function expectAny($conn, array $codes): void {
        $resp = $this->read($conn);
        $got  = (int) substr($resp, 0, 3);
        if (!in_array($got, $codes, true)) {
            throw new \RuntimeException("SMTP unexpected code {$got}: " . trim($resp));
        }
    }

    private function encodeHeader(string $v): string {
        return preg_match('/[^\x20-\x7E]/', $v)
            ? '=?UTF-8?B?' . base64_encode($v) . '?='
            : $v;
    }

    private function htmlToText(string $html): string {
        $t = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $t = preg_replace('/<\/p>/i',      "\n\n", $t);
        $t = preg_replace('/<\/div>/i',    "\n",   $t);
        $t = preg_replace('/<\/li>/i',     "\n",   $t);
        $t = preg_replace('/<\/h[1-6]>/i', "\n\n", $t);
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('/[ \t]+/', ' ', $t);
        $t = preg_replace('/\n{3,}/', "\n\n", $t);
        return trim($t);
    }
}
