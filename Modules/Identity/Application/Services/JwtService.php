<?php

namespace Modules\Identity\Application\Services;

class JwtService
{
    /**
     * The signature secret key.
     */
    protected string $secret;

    public function __construct()
    {
        $this->secret = config('app.key', 'SomeDefaultBackupSecretKeyStr123!');
    }

    /**
     * Encode payload into a JWT access token.
     */
    public function encode(array $payload, int $ttlSeconds = 900): string
    {
        $header = json_encode(['alg' => 'HS256', 'typ' => 'JWT']);
        
        $payload['exp'] = time() + $ttlSeconds;
        $payload['iat'] = time();

        $base64Header = $this->base64UrlEncode($header);
        $base64Payload = $this->base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $this->secret, true);
        $base64Signature = $this->base64UrlEncode($signature);

        return "{$base64Header}.{$base64Payload}.{$base64Signature}";
    }

    /**
     * Decode a JWT access token. Returns null if invalid or expired.
     */
    public function decode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        list($base64Header, $base64Payload, $base64Signature) = $parts;

        $signature = $this->base64UrlDecode($base64Signature);
        $expectedSignature = hash_hmac('sha256', "{$base64Header}.{$base64Payload}", $this->secret, true);

        if (!hash_equals($signature, $expectedSignature)) {
            return null;
        }

        $payload = json_decode($this->base64UrlDecode($base64Payload), true);

        if (!$payload || !isset($payload['exp']) || $payload['exp'] < time()) {
            return null; // Expired or malformed
        }

        return $payload;
    }

    /**
     * Base64Url encode.
     */
    protected function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    /**
     * Base64Url decode.
     */
    protected function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}
