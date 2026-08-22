<?php
declare(strict_types=1);

/**
 * Vercel runs PHP through one serverless entrypoint. Keep the public PHP
 * surface explicit so configuration and database maintenance scripts cannot
 * be executed over HTTP.
 */
$publicEndpoints = [
    "Exercise_Tracker/deleteWorkout.php",
    "Exercise_Tracker/exerciseTracker.php",
    "Exercise_Tracker/getExercises.php",
    "Exercise_Tracker/processExercises.php",
    "Exercise_Tracker/viewWorkouts.php",
    "Friends/friends.php",
    "Friends/processFriends.php",
    "Gym_Locator/save_gym.php",
    "Login_FAQs/admin_dashboard.php",
    "Login_FAQs/leaderboard.php",
    "Login_FAQs/api/admin_faqs.php",
    "Login_FAQs/api/admin_friendships.php",
    "Login_FAQs/api/gemini_chat.php",
    "Login_FAQs/api/get_faqs.php",
    "Login_FAQs/api/leaderboard_summary.php",
    "Login_FAQs/api/login.php",
    "Login_FAQs/api/logout.php",
    "Login_FAQs/api/me.php",
    "Login_FAQs/api/register.php",
    "Profile/delete-account.php",
    "Profile/profile.php",
    "Profile/update-bio.php",
    "Profile/update-fitness.php",
    "Profile/update-pfp.php",
    "Profile/update-user.php",
];

$requestedFile = $_GET["__vercel_file"] ?? null;
if (!is_string($requestedFile)) {
    http_response_code(400);
    echo "Missing PHP route.";
    exit;
}

unset($_GET["__vercel_file"], $_REQUEST["__vercel_file"]);
$requestedFile = ltrim(rawurldecode(str_replace("\\", "/", $requestedFile)), "/");

if (!in_array($requestedFile, $publicEndpoints, true)) {
    http_response_code(404);
    echo "Not found.";
    exit;
}

$projectRoot = realpath(dirname(__DIR__));
$target = $projectRoot === false ? false : realpath($projectRoot . "/" . $requestedFile);

if (
    $projectRoot === false
    || $target === false
    || !is_file($target)
    || !str_starts_with($target, $projectRoot . DIRECTORY_SEPARATOR)
) {
    http_response_code(404);
    echo "Not found.";
    exit;
}

require_once $projectRoot . "/DatabaseConnection.php";
if (app_database_session_enabled() && session_status() === PHP_SESSION_NONE) {
    require_once $projectRoot . "/DatabaseSessionHandler.php";
    session_set_save_handler(new DatabaseSessionHandler(), true);
}

$scriptName = "/" . $requestedFile;
$queryString = http_build_query($_GET);
$_SERVER["SCRIPT_FILENAME"] = $target;
$_SERVER["SCRIPT_NAME"] = $scriptName;
$_SERVER["PHP_SELF"] = $scriptName;
$_SERVER["QUERY_STRING"] = $queryString;
$_SERVER["REQUEST_URI"] = $scriptName . ($queryString === "" ? "" : "?" . $queryString);

chdir($projectRoot);
require $target;
