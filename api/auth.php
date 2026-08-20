<?php
require_once __DIR__ . '/config.php';

class JWT {
    public static function encode(array $payload): string {
        $header = [
            'typ' => 'JWT',
            'alg' => JWT_ALGORITHM
        ];

        $payload['iat'] = time();
        $payload['exp'] = time() + JWT_EXPIRATION;

        $segments = [];
        $segments[] = self::base64UrlEncode(json_encode($header));
        $segments[] = self::base64UrlEncode(json_encode($payload));

        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, JWT_SECRET, true);
        $segments[] = self::base64UrlEncode($signature);

        return implode('.', $segments);
    }

    public static function decode(string $token): ?array {
        $segments = explode('.', $token);
        if (count($segments) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $segments;

        // Verify signature
        $signingInput = "$headerB64.$payloadB64";
        $signature = self::base64UrlDecode($signatureB64);
        $expectedSignature = hash_hmac('sha256', $signingInput, JWT_SECRET, true);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($payloadB64), true);
        if (!$payload) {
            return null;
        }

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

class Auth {
    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT);
    }

    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    public static function signToken(array $payload): string {
        return JWT::encode($payload);
    }

    public static function verifyToken(string $token): ?array {
        return JWT::decode($token);
    }

    public static function setCookie(string $token): void {
        setcookie(
            COOKIE_NAME,
            $token,
            [
                'expires' => time() + COOKIE_MAX_AGE,
                'path' => COOKIE_PATH,
                'secure' => COOKIE_SECURE,
                'httponly' => COOKIE_HTTPONLY,
                'samesite' => COOKIE_SAMESITE
            ]
        );
    }

    public static function clearCookie(): void {
        setcookie(COOKIE_NAME, '', [
            'expires' => time() - 3600,
            'path' => COOKIE_PATH
        ]);
    }

    public static function getTokenFromCookie(): ?string {
        return $_COOKIE[COOKIE_NAME] ?? null;
    }

    public static function getCurrentUser(): ?array {
        $token = self::getTokenFromCookie();
        if (!$token) {
            return null;
        }
        return self::verifyToken($token);
    }

    public static function requireAuth(): array {
        $user = self::getCurrentUser();
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'Требуется авторизация']);
            exit;
        }
        return $user;
    }

    public static function requireAdmin(): array {
        $user = self::requireAuth();
        if ($user['role'] !== 'admin') {
            http_response_code(403);
            echo json_encode(['error' => 'Доступ запрещён']);
            exit;
        }
        return $user;
    }

    public static function optionalAuth(): ?array {
        return self::getCurrentUser();
    }
}
