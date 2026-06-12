<?php
// Show errors for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

$database_config = [
    "host" => getenv("MYSQL_HOST") ?: "localhost",
    "user" => getenv("MYSQL_USERNAME") ?: "root",
    "pass" => getenv("MYSQL_PASSWORD") ?: "",
    "name" => getenv("MYSQL_DATABASE") ?: "motiv8",
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
        "mysql:host={$database_config["host"]};dbname={$database_config["name"]};charset=utf8",
        $database_config["user"],
        $database_config["pass"]
    );
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
