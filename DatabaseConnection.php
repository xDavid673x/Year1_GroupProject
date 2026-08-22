<?php
declare(strict_types=1);

/**
 * Return the shared database configuration used by every PHP entrypoint.
 * TiDB Cloud's Vercel integration supplies TIDB_* variables; MYSQL_* remains
 * supported for local MySQL and other hosts.
 *
 * @return array{
 *   host: string,
 *   port: int,
 *   database: string,
 *   username: string,
 *   password: string,
 *   charset: string,
 *   timeout_seconds: int,
 *   ssl_ca: string,
 *   ssl_verify_server_cert: bool
 * }
 */
function app_database_config(): array
{
    static $config = null;
    if (is_array($config)) {
        return $config;
    }

    $tidbHost = (string) (getenv("TIDB_HOST") ?: "");
    $sslCa = (string) (getenv("MYSQL_SSL_CA") ?: getenv("TIDB_SSL_CA") ?: "");
    if ($sslCa === "" && $tidbHost !== "") {
        $sslCa = __DIR__ . "/certs/isrg-root-x1.pem";
    }

    $config = [
        "host" => $tidbHost !== "" ? $tidbHost : (string) (getenv("MYSQL_HOST") ?: "localhost"),
        "port" => (int) (getenv("TIDB_PORT") ?: getenv("MYSQL_PORT") ?: 3306),
        "database" => (string) (getenv("TIDB_DATABASE") ?: getenv("MYSQL_DATABASE") ?: "motiv8"),
        "username" => (string) (getenv("TIDB_USER") ?: getenv("MYSQL_USERNAME") ?: "root"),
        "password" => (string) (getenv("TIDB_PASSWORD") ?: getenv("MYSQL_PASSWORD") ?: ""),
        "charset" => "utf8mb4",
        "timeout_seconds" => max(1, (int) (getenv("MYSQL_TIMEOUT_SECONDS") ?: 5)),
        "ssl_ca" => $sslCa,
        "ssl_verify_server_cert" => true,
    ];

    $rootLocalPath = __DIR__ . "/DatabaseInit.local.php";
    if (is_file($rootLocalPath)) {
        $local = require $rootLocalPath;
        if (is_array($local)) {
            $config = array_replace($config, [
                "host" => (string) ($local["host"] ?? $config["host"]),
                "port" => (int) ($local["port"] ?? $config["port"]),
                "database" => (string) ($local["name"] ?? $local["database"] ?? $config["database"]),
                "username" => (string) ($local["user"] ?? $local["username"] ?? $config["username"]),
                "password" => (string) ($local["pass"] ?? $local["password"] ?? $config["password"]),
                "timeout_seconds" => max(1, (int) ($local["timeout_seconds"] ?? $config["timeout_seconds"])),
                "ssl_ca" => (string) ($local["ssl_ca"] ?? $config["ssl_ca"]),
            ]);
        }
    }

    return $config;
}

function app_database_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = app_database_config();
    $dsn = sprintf(
        "mysql:host=%s;port=%d;dbname=%s;charset=%s",
        $db["host"],
        $db["port"],
        $db["database"],
        $db["charset"]
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => $db["timeout_seconds"],
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if ($db["ssl_ca"] !== "") {
        if (!is_file($db["ssl_ca"])) {
            throw new RuntimeException("The configured database CA certificate was not found.");
        }
        if (!defined("PDO::MYSQL_ATTR_SSL_CA") || !defined("PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT")) {
            throw new RuntimeException("The PDO MySQL TLS extension is unavailable.");
        }

        $options[PDO::MYSQL_ATTR_SSL_CA] = $db["ssl_ca"];
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $db["ssl_verify_server_cert"];
    }

    $pdo = new PDO($dsn, $db["username"], $db["password"], $options);
    return $pdo;
}

function app_database_session_enabled(): bool
{
    $driver = strtolower((string) (getenv("SESSION_DRIVER") ?: ""));
    if ($driver !== "") {
        return $driver === "database";
    }

    $hasHostedDatabase = getenv("TIDB_HOST") !== false || getenv("MYSQL_HOST") !== false;
    return getenv("VERCEL") === "1" && $hasHostedDatabase;
}
