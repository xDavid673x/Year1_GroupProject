<?php
declare(strict_types=1);

require __DIR__ . "/bootstrap.php";
require __DIR__ . "/mysql.php";
require __DIR__ . "/mongo.php";

require_method("POST");

$data = get_request_data();
$username = trim((string) ($data["username"] ?? $data["name"] ?? ""));
$email = strtolower(trim((string) ($data["email"] ?? "")));
$password = (string) ($data["password"] ?? "");
$confirmPassword = (string) ($data["confirmPassword"] ?? "");
$PhoneNum = trim((string) ($data["PhoneNum"] ?? $data["phoneNum"] ?? ""));

if (strlen($username) < 2) {
    json_response(["error" => "Please enter a username."], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(["error" => "Please enter a valid email."], 422);
}

if (strlen($password) < 8) {
    json_response(["error" => "Password must be at least 8 characters."], 422);
}

if ($password !== $confirmPassword) {
    json_response(["error" => "Passwords do not match."], 422);
}

if ($PhoneNum === "") {
    json_response(["error" => "Please enter your phone number."], 422);
}

$passwordhash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo = mysql_pdo();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        "INSERT INTO Users (Username, Email, PasswordHash, PhoneNum) VALUES (:username, :email, :passwordhash, :PhoneNum)"
    );
    $stmt->execute([
        ":username" => $username,
        ":email" => $email,
        ":passwordhash" => $passwordhash,
        ":PhoneNum" => $PhoneNum,
    ]);

    $userId = (int) $pdo->lastInsertId();
    $profile = $pdo->prepare(
        "INSERT INTO Profiles (userid, displayname) VALUES (:userid, :displayname)"
    );
    $profile->execute([
        ":userid" => $userId,
        ":displayname" => $username,
    ]);
    $pdo->commit();
} catch (PDOException $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() === "23000") {
        json_response(["error" => "That email or username is already registered."], 409);
    }
    json_response(["error" => "Failed to create account."], 500);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_response(["error" => "Failed to create account."], 500);
}

try {
    $collection = mongo_users_collection();
    if ($collection) {
        $profile = [
            "mysql_user_id" => $userId,
            "email" => $email,
            "name" => $username,
            "phone_num" => $PhoneNum,
            "created_at" => date(DATE_ATOM),
        ];
        $collection->insertOne($profile);
    }
} catch (Throwable $e) {
    // Mongo profile creation is optional for auth flow.
}

session_regenerate_id(true);
$_SESSION["user_id"] = $userId;
$_SESSION["name"] = $username;
$_SESSION["email"] = $email;
$_SESSION["role"] = "member";

json_response([
    "ok" => true,
    "user" => [
        "id" => $userId,
        "username" => $username,
        "name" => $username,
        "email" => $email,
        "PhoneNum" => $PhoneNum,
        "role" => "member",
    ],
], 201);
