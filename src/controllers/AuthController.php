<?php
class AuthController
{
    public static function handleLogin(): void
    {
        header('Content-Type: application/json');

        // Rate limit: 5 attempts per 5 min per IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (!Security::rateLimit("login_{$ip}", 5, 300)) {
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Sobrang daming pagsubok. Subukan ulit pagkatapos ng 5 minuto.']);
            return;
        }

        // CSRF verification
        $csrf = Security::sanitizeString($_POST['csrf_token'] ?? '');
        if (!Security::verifyCsrf($csrf)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid security token. I-refresh ang page.']);
            return;
        }

        $username = Security::sanitizeString($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Username at password ay kinakailangan.']);
            return;
        }

        // Fetch user with prepared statement (SQL Injection safe)
        $user = Database::query(
            "SELECT id, username, email, password, full_name, role, is_active FROM users WHERE username = ? LIMIT 1",
            [$username]
        )->fetch();

        if (!$user || !Security::verifyPassword($password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'Mali ang username o password.']);
            return;
        }

        if (!$user['is_active']) {
            echo json_encode(['success' => false, 'message' => 'Ang account ay hindi aktibo.']);
            return;
        }

        Auth::login($user);

        // Update last login
        Database::query("UPDATE users SET last_login = NOW() WHERE id = ?", [$user['id']]);

        // Audit log
        Database::query(
            "INSERT INTO audit_log (user_id, action, ip_address, details) VALUES (?,?,?,?)",
            [$user['id'], 'LOGIN', $ip, "User {$username} logged in"]
        );

        echo json_encode(['success' => true, 'redirect' => '/sari-pos/index.php?page=dashboard']);
    }
}
