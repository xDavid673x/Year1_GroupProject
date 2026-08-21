<?php
// Show connection details locally, but never expose them from a deployment.
$is_production = getenv("APP_ENV") === "production" || getenv("VERCEL") === "1";
ini_set("display_errors", $is_production ? "0" : "1");
error_reporting(E_ALL);

$database_config = [
    "host" => getenv("MYSQL_HOST") ?: "localhost",
    "port" => (int) (getenv("MYSQL_PORT") ?: 3306),
    "user" => getenv("MYSQL_USERNAME") ?: "root",
    "pass" => getenv("MYSQL_PASSWORD") ?: "",
    "name" => getenv("MYSQL_DATABASE") ?: "motiv8",
    "timeout_seconds" => (int) (getenv("MYSQL_TIMEOUT_SECONDS") ?: 5),
];

$local_config_path = __DIR__ . "/DatabaseInit.local.php";
if (is_file($local_config_path)) {
    $local_config = require $local_config_path;
    if (is_array($local_config)) {
        $database_config = array_replace($database_config, $local_config);
    }
}

// Connect to the database using PDO
try {
    $pdo = new PDO(
        "mysql:host={$database_config["host"]};port={$database_config["port"]};dbname={$database_config["name"]};charset=utf8mb4",
        $database_config["user"],
        $database_config["pass"],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => max(1, (int) $database_config["timeout_seconds"]),
        ]
    );
} catch (PDOException $e) {
    if ($is_production) {
        error_log("Database connection failed: " . $e->getMessage());
        http_response_code(500);
        die("Database unavailable.");
    }

    die("Database error: " . $e->getMessage());
}
?>
