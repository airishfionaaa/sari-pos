<?php
/**
 * Database — PDO singleton.
 * Uses prepared statements everywhere → SQL Injection prevention.
 * Port must be 3306–3310 as per requirements.
 */
class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone()    {}

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $host   = EnvLoader::get('DB_HOST', 'casestudy');
            $port   = (int) EnvLoader::get('DB_PORT', 3310);
            $dbname = EnvLoader::get('DB_NAME', 'sari_pos');
            $user   = EnvLoader::get('DB_USER', 'root');
            $pass   = EnvLoader::get('DB_PASS', 'radeon2024');

            // Requirement: port must be 3306–3310
            if ($port < 3306 || $port > 3310) {
                throw new RuntimeException(
                    "DB_PORT must be between 3306 and 3310. Configured: {$port}"
                );
            }

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

            self::$instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // real prepared statements
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        }
        return self::$instance;
    }

    /** Prepare + execute → returns PDOStatement */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function lastId(): string
    {
        return self::getInstance()->lastInsertId();
    }

    public static function beginTransaction(): void { self::getInstance()->beginTransaction(); }
    public static function commit(): void           { self::getInstance()->commit(); }
    public static function rollback(): void         { self::getInstance()->rollBack(); }
}
