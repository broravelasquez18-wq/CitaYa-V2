<?php
declare(strict_types=1);
namespace App;

final class Crypto
{
    public static function seal(string $text, string $key): string
    {
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($text, 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false) throw new \RuntimeException('Encryption failed');
        return base64_encode($iv . $tag . $cipher);
    }

    public static function open(string $text, string $key): string
    {
        $raw = base64_decode($text, true);
        if ($raw === false || strlen($raw) < 28) throw new \RuntimeException('Invalid encrypted payload');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', hash('sha256', $key, true), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        if ($plain === false) throw new \RuntimeException('Invalid encrypted payload');
        return $plain;
    }
}
