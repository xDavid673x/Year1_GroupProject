<?php
declare(strict_types=1);

require __DIR__ . "/bootstrap.php";
require __DIR__ . "/mysql.php";

require_method("POST");

$data = get_request_data();
$email = strtolower(trim((string) ($data["email"] ?? "")));
$password = (string) ($data["password"] ?? "");

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === "") {
    json_response(["error" => "Invalid email or password."], 422);
}

try {
    $pdo = mysql_pdo();
    $stmt = $pdo->prepare(
        "SELECT UserId AS id, Username AS username, Email AS email, PasswordHash AS passwordhash, PhoneNum, role
         FROM Users
         WHERE Email = :email
         LIMIT 1"
    );
    $stmt->execute([":email" => $email]);
    $user = $stmt->fetch();
} catch (Throwable $e) {
    json_response(["error" => "Login failed."], 500);
}

if (!$user || !password_verify($password, (string) $user["passwordhash"])) {
    json_response(["error" => "Invalid email or password."], 401);
}

session_regenerate_id(true);
$_SESSION["user_id"] = (int) $user["id"];
$_SESSION["name"] = (string) $user["username"];
$_SESSION["email"] = (string) $user["email"];
$_SESSION["role"] = (string) ($user["role"] ?? "member");

json_response([
    "ok" => true,
    "user" => [
        "id" => (int) $user["id"],
        "username" => (string) $user["username"],
        "name" => (string) $user["username"],
        "email" => (string) $user["email"],
        "PhoneNum" => (string) ($user["PhoneNum"] ?? ""),
        "role" => (string) ($user["role"] ?? "member"),
    ],
]);
