<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/src/helpers/EnvLoader.php';
EnvLoader::load(__DIR__ . '/.env');
require_once __DIR__ . '/config/database.php';

$password = 'Admin@123';
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

echo "New hash: " . $hash . "<br><br>";

// Auto-update sa database
Database::query(
    "UPDATE users SET password = ? WHERE username = 'admin'",
    [$hash]
);

echo "Password updated! Mag-login gamit Admin@123";