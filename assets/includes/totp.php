<?php
/**
 * TOTP / OTP helper — RFC 6238 compliant, compatible with Google Authenticator
 */
class TOTP
{
    private static string $ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(): string
    {
        $bytes  = random_bytes(20);
        $secret = '';
        $buf    = 0;
        $bits   = 0;
        foreach (str_split($bytes) as $byte) {
            $buf  = ($buf << 8) | ord($byte);
            $bits += 8;
            while ($bits >= 5) {
                $bits  -= 5;
                $secret .= self::$ALPHABET[($buf >> $bits) & 0x1F];
            }
        }
        return $secret;
    }

    private static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[\s=]/', '', $secret));
        $buf    = 0;
        $bits   = 0;
        $result = '';
        foreach (str_split($secret) as $c) {
            $pos = strpos(self::$ALPHABET, $c);
            if ($pos === false) continue;
            $buf   = ($buf << 5) | $pos;
            $bits += 5;
            if ($bits >= 8) {
                $bits  -= 8;
                $result .= chr(($buf >> $bits) & 0xFF);
            }
        }
        return $result;
    }

    /**
     * Hitung kode TOTP untuk timestamp tertentu
     * Menggunakan RFC 6238: HOTP(K, T) dengan T = floor(Unix_time / period)
     */
    public static function getCode(string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $period = defined('OTP_PERIOD') ? OTP_PERIOD : 30;
        $digits = defined('OTP_DIGITS') ? OTP_DIGITS : 6;

        // T sebagai 8-byte big-endian integer
        $t    = (int) floor($timestamp / $period);
        $time = pack('N*', 0) . pack('N*', $t);   // 4 bytes 0 + 4 bytes T

        $key  = self::base32Decode($secret);
        if (!$key) return str_pad('0', $digits, '0');

        $hash   = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0x0F;

        $code = (
            ((ord($hash[$offset])     & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) <<  8) |
             (ord($hash[$offset + 3]) & 0xFF)
        ) % (10 ** $digits);

        return str_pad((string) $code, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Verifikasi kode OTP
     * drift=2 berarti toleransi ±60 detik — cukup untuk clock skew normal HP vs server
     */
    public static function verify(string $secret, string $code, int $drift = 2): bool
    {
        if (!$secret || strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }
        $now    = time();
        $period = defined('OTP_PERIOD') ? OTP_PERIOD : 30;
        for ($i = -$drift; $i <= $drift; $i++) {
            if (hash_equals(self::getCode($secret, $now + $i * $period), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function getUri(string $secret, string $email, string $issuer = ''): string
    {
        if (!$issuer) $issuer = defined('OTP_ISSUER') ? OTP_ISSUER : 'SignKu';
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $email)
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1'
            . '&digits=' . (defined('OTP_DIGITS') ? OTP_DIGITS : 6)
            . '&period=' . (defined('OTP_PERIOD') ? OTP_PERIOD : 30);
    }

    /** Return OTP URI string — QR di-render oleh qrcode.js di browser */
    public static function getQrDataUri(string $secret, string $email): string
    {
        return self::getUri($secret, $email);
    }
}
