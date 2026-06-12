<?php
declare(strict_types=1);

function mysql_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = require __DIR__ . "/config.php";
    $db = $config["mysql"];
    $host = (string) ($db["host"] ?? "127.0.0.1");
    $port = (int) ($db["port"] ?? 3306);
    $database = (string) ($db["database"] ?? "");
    $charset = (string) ($db["charset"] ?? "utf8mb4");
    $username = (string) ($db["username"] ?? "");
    $password = (string) ($db["password"] ?? "");
    $timeoutSeconds = max(1, (int) ($db["timeout_seconds"] ?? 5));

    $dsn = sprintf(
        "mysql:host=%s;port=%d;dbname=%s;charset=%s",
        $host,
        $port,
        $database,
        $charset
    );

    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => $timeoutSeconds,
        ]
    );

    return $pdo;
}
