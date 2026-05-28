<?php
date_default_timezone_set('Asia/Jakarta');

class TOTP {
    private static $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret($length = 32) {
        $secret = '';
        for ($i=0;$i<$length;$i++) {
            $secret .= self::$chars[random_int(0,31)];
        }
        return $secret;
    }

    private static function base32Decode($secret) {
        if (empty($secret)) return '';

        $secret = strtoupper($secret);
        $secret = preg_replace('/[^A-Z2-7]/', '', $secret);

        $binary = '';
        for ($i=0;$i<strlen($secret);$i++) {
            $current = strpos(self::$chars, $secret[$i]);
            $binary .= str_pad(decbin($current), 5, '0', STR_PAD_LEFT);
        }

        $result = '';
        for ($i=0;$i<strlen($binary);$i+=8) {
            $byte = substr($binary, $i, 8);
            if (strlen($byte) === 8) {
                $result .= chr(bindec($byte));
            }
        }

        return $result;
    }

    public static function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = floor(time() / 30);
        }

        $secretKey = self::base32Decode($secret);

        $time = chr(0).chr(0).chr(0).chr(0).pack('N*', $timeSlice);

        $hash = hash_hmac('SHA1', $time, $secretKey, true);

        $offset = ord(substr($hash, -1)) & 0x0F;

        $truncatedHash =
            ((ord($hash[$offset]) & 0x7F) << 24 ) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16 ) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8 ) |
            (ord($hash[$offset + 3]) & 0xFF);

        $code = $truncatedHash % 1000000;

        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }

    public static function verify($secret, $code, $discrepancy = 2) {
        $currentTimeSlice = floor(time() / 30);

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTimeSlice + $i);

            if (hash_equals($calculatedCode, trim($code))) {
                return true;
            }
        }

        return false;
    }

    public static function getUri($secret, $email, $issuer = 'SignKu') {
        return 'otpauth://totp/' .
            rawurlencode($issuer . ':' . $email) .
            '?secret=' . $secret .
            '&issuer=' . rawurlencode($issuer) .
            '&algorithm=SHA1&digits=6&period=30';
    }

    public static function getQrDataUri($secret, $email) {
        return self::getUri($secret, $email);
    }
}
