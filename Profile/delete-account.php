<?php
session_start();
require __DIR__ . "/../DatabaseInit.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: ../Login_FAQs/login.html");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit;
}

$userid = (int) $_SESSION['user_id'];

$pdo->prepare("DELETE FROM Profiles WHERE userid = ?")->execute([$userid]); // del profile hen user
$pdo->prepare("DELETE FROM Users WHERE userid = ?")->execute([$userid]);

// Destroy the sesh
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

header("Location: ../Login_FAQs/login.html");
exit;
?>