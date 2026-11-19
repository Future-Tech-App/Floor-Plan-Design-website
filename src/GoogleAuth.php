<?php
declare(strict_types=1);

/**
 * Verifies "Sign in with Google" ID tokens (Google Identity Services) without any library.
 *
 * The browser gets a signed JWT from Google; we check its RS256 signature against Google's
 * published keys (cached in data/google_certs.json for as long as Google allows), then the
 * issuer, audience (our OAuth client id), expiry and that the email address is verified.
 *
 * The client id comes from Admin → Site Settings (google_client_id) or config('google_client_id').
 */
final class GoogleAuth
{
    private const CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';
    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];
    private const LEEWAY = 120; // seconds of clock difference allowed

    public static function clientId(): string
    {
        $fromAdmin = trim((string) Repository::setting('google_client_id', ''));
        return $fromAdmin !== '' ? $fromAdmin : trim((string) config('google_client_id', ''));
    }

    public static function configured(): bool
    {
        return (bool) preg_match('/^[0-9]+-[a-z0-9]+\.apps\.googleusercontent\.com$/', self::clientId());
    }

    /**
     * Returns the token's claims (sub, email, name, picture, ...) or throws RuntimeException.
     *
     * @param array|null $jwks keys to check against (tests); null fetches Google's keys
     */
    public static function verify(string $jwt, ?string $clientId = null, ?array $jwks = null, ?int $now = null): array
    {
        $clientId ??= self::clientId();
        $now ??= time();
        $parts = explode('.', $jwt);
        if (count($parts) !== 3 || strlen($jwt) > 8192) {
            throw new RuntimeException('Malformed token.');
        }
        [$h64, $p64, $s64] = $parts;
        $header = json_decode(self::b64($h64), true);
        $claims = json_decode(self::b64($p64), true);
        $sig = self::b64($s64);
        if (!is_array($header) || !is_array($claims) || $sig === '') {
            throw new RuntimeException('Malformed token.');
        }
        if (($header['alg'] ?? '') !== 'RS256' || !is_string($header['kid'] ?? null)) {
            throw new RuntimeException('Unsupported token.');
        }

        $key = self::findKey($header['kid'], $jwks);
        if ($key === null) {
            throw new RuntimeException('Unknown signing key.');
        }
        $pub = openssl_pkey_get_public(self::jwkToPem($key));
        if ($pub === false || openssl_verify($h64 . '.' . $p64, $sig, $pub, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('Bad signature.');
        }

        if (!in_array($claims['iss'] ?? '', self::ISSUERS, true)) {
            throw new RuntimeException('Wrong issuer.');
        }
        $aud = $claims['aud'] ?? '';
        if ($clientId === '' || !(is_string($aud) ? hash_equals($clientId, $aud) : in_array($clientId, (array) $aud, true))) {
            throw new RuntimeException('Token is for another app.');
        }
        if ((int) ($claims['exp'] ?? 0) < $now - self::LEEWAY || (int) ($claims['iat'] ?? 0) > $now + self::LEEWAY) {
            throw new RuntimeException('Token expired.');
        }
        if (!is_string($claims['sub'] ?? null) || $claims['sub'] === '' || !filter_var($claims['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Token has no account.');
        }
        $verified = $claims['email_verified'] ?? false;
        if ($verified !== true && $verified !== 'true') {
            throw new RuntimeException('Email not verified.');
        }
        return $claims;
    }

    private static function b64(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4), true);
    }

    private static function findKey(string $kid, ?array $jwks): ?array
    {
        foreach (($jwks ?? self::googleKeys(false))['keys'] ?? [] as $k) {
            if (($k['kid'] ?? '') === $kid && ($k['kty'] ?? '') === 'RSA') {
                return $k;
            }
        }
        // Google rotates keys; refresh once if the key is new to us.
        if ($jwks === null) {
            foreach (self::googleKeys(true)['keys'] ?? [] as $k) {
                if (($k['kid'] ?? '') === $kid && ($k['kty'] ?? '') === 'RSA') {
                    return $k;
                }
            }
        }
        return null;
    }

    /** Google's current public keys, cached on disk until the time Google's Cache-Control allows. */
    private static function googleKeys(bool $refresh): array
    {
        $file = ROOT_DIR . '/data/google_certs.json';
        if (!$refresh && is_file($file)) {
            $cached = json_decode((string) file_get_contents($file), true);
            if (is_array($cached) && ($cached['expires'] ?? 0) > time()) {
                return $cached['jwks'];
            }
        }
        $ch = curl_init(self::CERTS_URL);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_HEADER => true]);
        $res = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        if (!is_string($res) || $code !== 200) {
            throw new RuntimeException('Could not reach Google to check the sign-in.');
        }
        $jwks = json_decode(substr($res, $headerSize), true);
        if (!is_array($jwks) || empty($jwks['keys'])) {
            throw new RuntimeException('Could not read Google keys.');
        }
        $maxAge = preg_match('/max-age=(\d+)/i', substr($res, 0, $headerSize), $m) ? (int) $m[1] : 3600;
        @file_put_contents($file, json_encode(['expires' => time() + min($maxAge, 86400), 'jwks' => $jwks]), LOCK_EX);
        return $jwks;
    }

    /** RSA JWK (n, e) → PEM public key. */
    private static function jwkToPem(array $jwk): string
    {
        $n = self::b64((string) ($jwk['n'] ?? ''));
        $e = self::b64((string) ($jwk['e'] ?? ''));
        $int = static function (string $b): string { // DER INTEGER: no leading zeros, but positive
            $b = ltrim($b, "\0");
            if ($b === '' || ord($b[0]) > 0x7f) {
                $b = "\0" . $b;
            }
            return "\x02" . self::derLen(strlen($b)) . $b;
        };
        $body = $int($n) . $int($e);
        $rsaKey = "\x30" . self::derLen(strlen($body)) . $body;
        $bitString = "\x03" . self::derLen(strlen($rsaKey) + 1) . "\0" . $rsaKey;
        $algo = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00"; // rsaEncryption OID + NULL
        $spki = "\x30" . self::derLen(strlen($algo . $bitString)) . $algo . $bitString;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    private static function derLen(int $len): string
    {
        if ($len < 0x80) {
            return chr($len);
        }
        $out = '';
        while ($len > 0) {
            $out = chr($len & 0xff) . $out;
            $len >>= 8;
        }
        return chr(0x80 | strlen($out)) . $out;
    }
}
