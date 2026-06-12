<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = !empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";
    session_set_cookie_params([
        "httponly" => true,
        "secure" => $isHttps,
        "samesite" => "Lax",
    ]);
    session_start();
}

header("Content-Type: application/json; charset=utf-8");

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload);
    exit;
}

function get_request_data(): array
{
    $contentType = $_SERVER["CONTENT_TYPE"] ?? "";

    if (stripos($contentType, "application/json") !== false) {
        $raw = file_get_contents("php://input") ?: "";
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    return $_POST;
}

function require_method(string $method): void
{
    if (($_SERVER["REQUEST_METHOD"] ?? "") !== strtoupper($method)) {
        json_response(["error" => "Method not allowed."], 405);
    }
}

function current_user_from_session(): ?array
{
    if (empty($_SESSION["user_id"]) || empty($_SESSION["email"])) {
        return null;
    }

    return [
        "id" => (int) $_SESSION["user_id"],
        "name" => (string) ($_SESSION["name"] ?? ""),
        "email" => (string) $_SESSION["email"],
        "role" => (string) ($_SESSION["role"] ?? "member"),
    ];
}

function require_auth_user(): array
{
    $user = current_user_from_session();
    if (!$user) {
        json_response(["error" => "Unauthorized."], 401);
    }
    return $user;
}

function require_admin(): array
{
    $user = require_auth_user();
    if (($user["role"] ?? "member") !== "admin") {
        json_response(["error" => "Forbidden."], 403);
    }
    return $user;
}
