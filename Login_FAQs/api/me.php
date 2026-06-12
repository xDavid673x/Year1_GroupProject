<?php
declare(strict_types=1);

require __DIR__ . "/bootstrap.php";

if (($_SERVER["REQUEST_METHOD"] ?? "") !== "GET") {
    json_response(["error" => "Method not allowed."], 405);
}

$user = current_user_from_session();

if (!$user) {
    json_response(["authenticated" => false]);
}

json_response([
    "authenticated" => true,
    "user" => $user,
]);
