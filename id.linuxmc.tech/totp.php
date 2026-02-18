<?php
// TOTP Helper Class (Time-Based One-Time Password)
// Robust Implementation

class TOTP {
    
    // Generiert ein zufälliges Secret (16 Zeichen Base32 = 80 Bits Entropie, Standard fuer Google Auth)
    public static function createSecret($length = 16) {
        $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $base32Chars[random_int(0, 31)];
        }
        return $secret;
    }

    private static function get_time_slice() {
        return floor(time() / 30);
    }

    // Berechnet den Code
    public static function getCode($secret, $timeSlice = null) {
        if ($timeSlice === null) {
            $timeSlice = self::get_time_slice();
        }

        $secretKey = self::base32Decode($secret);
        
        // Pack time into binary string (8 bytes, big-endian)
        // Correct packing: 4 bytes of 0 padding, then 4 bytes of timeSlice
        $time = pack('N', 0) . pack('N', $timeSlice);
        
        // HMAC-SHA1
        $hmac = hash_hmac('sha1', $time, $secretKey, true);
        
        // Ensure proper byte access
        $offset = ord($hmac[19]) & 0xf;
        
        // Read 4 bytes starting at offset
        $hashPart = substr($hmac, $offset, 4);
        
        // Unpack big-endian unsigned long
        $value = unpack('N', $hashPart);
        $value = $value[1];
        
        // Mask most significant bit
        $value = $value & 0x7FFFFFFF;
        
        // Modulo 10^6
        $modulo = $value % 1000000;
        
        return str_pad($modulo, 6, '0', STR_PAD_LEFT);
    }

    public static function verifyCode($secret, $code) {
        // Toleranz: +/- 1 Fenster (30s) = insgesamt 90s Gueltigkeit
        $slice = self::get_time_slice();
        
        // Wir prüfen: Aktuell, Vorher (-1), Nachher (+1)
        for ($i = -1; $i <= 1; $i++) {
            $check = self::getCode($secret, $slice + $i);
            if (hash_equals($check, $code)) {
                return true;
            }
        }
        return false;
    }

    // Hilfsfunktion: Robuste Base32 Dekodierung
    private static function base32Decode($secret) {
        if (empty($secret)) return '';
        
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));
        
        $secret = strtoupper($secret);
        $secret = rtrim($secret, '='); // Remove padding chars

        $buffer = 0;
        $bufferSize = 0;
        $result = '';

        for ($i = 0; $i < strlen($secret); $i++) {
            $char = $secret[$i];
            if (!isset($base32charsFlipped[$char])) continue;
            
            $val = $base32charsFlipped[$char];
            
            $buffer = ($buffer << 5) | $val;
            $bufferSize += 5;
            
            if ($bufferSize >= 8) {
                $bufferSize -= 8;
                $result .= chr(($buffer >> $bufferSize) & 0xFF);
            }
        }
        
        return $result;
    }
}
?>