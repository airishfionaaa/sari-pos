<?php
/**
 * Security — CSRF, XSS, password hashing, input sanitization, rate limiting.
 */
class Security
{
    private const TOKEN_TTL = 3600;

    // ── CSRF (Session-based, no DB required) ──────────────────

    public static function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfField(): string
    {
        $t = self::csrfToken();
        return '<input type="hidden" name="csrf_token" value="' . self::e($t) . '">';
    }

    public static function verifyCsrf(string $submitted): bool
    {
        if (empty($submitted)) return false;
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $submitted);
    }

    // ── XSS Prevention ────────────────────────────────────────

    public static function e(mixed $v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // ── Input Sanitization ────────────────────────────────────

    public static function sanitizeString(string $input): string
    {
        return trim(strip_tags($input));
    }

    public static function sanitizeInt(mixed $input): int
    {
        return (int) filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }

    public static function sanitizeFloat(mixed $input): float
    {
        return (float) filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    public static function sanitizeDate(string $input): string
    {
        $d = DateTime::createFromFormat('Y-m-d', $input);
        return ($d && $d->format('Y-m-d') === $input) ? $input : date('Y-m-d');
    }

    public static function isEmail(string $email): bool
    {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    // ── Password Hashing (Bcrypt) ─────────────────────────────

    public static function hashPassword(string $plain): string
    {
        $cost = (int) EnvLoader::get('BCRYPT_COST', 12);
        return password_hash($plain, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    public static function verifyPassword(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    // ── Rate Limiting (session-based) ─────────────────────────

    public static function rateLimit(string $key, int $max = 5, int $window = 300): bool
    {
        $k = "rl_{$key}";
        if (!isset($_SESSION[$k]) || time() > $_SESSION[$k]['reset']) {
            $_SESSION[$k] = ['count' => 0, 'reset' => time() + $window];
        }
        $_SESSION[$k]['count']++;
        return $_SESSION[$k]['count'] <= $max;
    }

    // ── HTTP Security Headers ─────────────────────────────────

    public static function setHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: default-src 'self'; "
             . "script-src 'self' 'unsafe-inline' https://js.pusher.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; "
             . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; "
             . "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; "
             . "img-src 'self' data:; "
             . "connect-src 'self' wss://*.pusher.com https://*.pusher.com;");
    }
}