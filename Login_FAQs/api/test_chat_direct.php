<?php
$_SERVER["REQUEST_METHOD"] = "POST";
$_SERVER["CONTENT_TYPE"] = "application/json";
$_SESSION["user_id"] = 1;
$_SESSION["email"] = "test@example.com";

// Override require_auth_user and get_request_data manually by including bootstrap but defining it before if we could.
// Actually, let's just make a copy of gemini_chat.php, remove the auth, and run it.
$content = file_get_contents("gemini_chat.php");
$content = str_replace('$authUser = require_auth_user();', '$authUser = ["id" => 1, "email" => "test@example.com"];', $content);
$content = str_replace('$data = get_request_data();', '$data = ["prompt" => "Hello", "history" => []];', $content);
file_put_contents("gemini_chat_test.php", $content);
