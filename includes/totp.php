<?php
class Totp {
    private string $secret;
    private int    $digits;
    private int    $period;

    public function __construct(string $base32Secret, int $digits = 6, int $period = 30) {
        $this->secret = $base32Secret;
        $this->digits = $digits;
        $this->period = $period;
    }

    public static function generateSecret(int $bytes = 20): string {
        return self::base32Encode(random_bytes($bytes));
    }

    public function verify(string $code, int $window = 1): bool {
        $counter = (int)floor(time() / $this->period);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals($this->getCode($counter + $i), $code)) return true;
        }
        return false;
    }

    public function getCode(?int $counter = null): string {
        if ($counter === null) $counter = (int)floor(time() / $this->period);
        $key  = self::base32Decode($this->secret);
        $msg  = pack('J', $counter);
        $hmac = hash_hmac('sha1', $msg, $key, true);
        $off  = ord($hmac[19]) & 0x0F;
        $code = (
            ((ord($hmac[$off])     & 0x7F) << 24) |
            ((ord($hmac[$off + 1]) & 0xFF) << 16) |
            ((ord($hmac[$off + 2]) & 0xFF) <<  8) |
            ( ord($hmac[$off + 3]) & 0xFF)
        ) % (10 ** $this->digits);
        return str_pad((string)$code, $this->digits, '0', STR_PAD_LEFT);
    }

    public function getUri(string $issuer, string $accountName): string {
        return 'otpauth://totp/'
            . rawurlencode($issuer . ':' . $accountName)
            . '?secret=' . rawurlencode($this->secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&digits=' . $this->digits
            . '&period=' . $this->period;
    }

    public function getSecret(): string { return $this->secret; }

    private static function base32Encode(string $data): string {
        static $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bin = '';
        foreach (str_split($data) as $c) $bin .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        $bin .= str_repeat('0', (5 - strlen($bin) % 5) % 5);
        $out = '';
        foreach (str_split($bin, 5) as $chunk) $out .= $alpha[bindec($chunk)];
        return $out;
    }

    private static function base32Decode(string $data): string {
        static $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper(preg_replace('/[=\s]/', '', $data));
        $bin  = '';
        foreach (str_split($data) as $c) {
            $pos = strpos($alpha, $c);
            if ($pos !== false) $bin .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bin, 8) as $chunk) {
            if (strlen($chunk) === 8) $out .= chr(bindec($chunk));
        }
        return $out;
    }
}
