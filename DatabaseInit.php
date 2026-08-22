<?php
declare(strict_types=1);

require_once __DIR__ . "/DatabaseConnection.php";

// Show connection details locally, but never expose them from a deployment.
$is_production = getenv("APP_ENV") === "production" || getenv("VERCEL") === "1";
ini_set("display_errors", $is_production ? "0" : "1");
error_reporting(E_ALL);

// Connect to the database using PDO
try {
    $pdo = app_database_pdo();
} catch (Throwable $e) {
    if ($is_production) {
        error_log("Database connection failed: " . $e->getMessage());
        http_response_code(500);
        die("Database unavailable.");
    }

    die("Database error: " . $e->getMessage());
}
?>
