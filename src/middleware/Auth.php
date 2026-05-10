<?php
/**
 * Auth — session-based authentication middleware.
 */
class Auth
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', 1);
            session_start();
        }
    }

    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public static function requireLogin(string $redirect = '/index.php?page=login'): void
    {
        if (!self::check()) {
            header("Location: {$redirect}");
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if ($_SESSION['role'] !== 'admin') {
            header('Location: /index.php?page=dashboard&error=unauthorized');
            exit;
        }
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);  // prevent session fixation
        $_SESSION['user_id']   = (int)$user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['login_at']  = time();
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function user(): array
    {
        return [
            'id'        => $_SESSION['user_id']   ?? null,
            'username'  => $_SESSION['username']  ?? 'Guest',
            'full_name' => $_SESSION['full_name']  ?? 'Guest',
            'role'      => $_SESSION['role']       ?? 'cashier',
        ];
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    public static function isAdmin(): bool
    {
        return ($_SESSION['role'] ?? '') === 'admin';
    }
}
