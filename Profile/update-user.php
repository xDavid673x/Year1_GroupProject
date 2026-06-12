<?php
session_start();
require __DIR__ . "/../DatabaseInit.php";

if (!isset($_SESSION['user_id'])) {
    die("User not logged in");
}

$userid = $_SESSION['user_id'];

$firstname = $_POST['firstname'] ?? '';
$lastname  = $_POST['lastname'] ?? '';
$password  = $_POST['password'] ?? '';

$displayname = trim($firstname . " " . $lastname); // Combine name because the DB uses displayname



// Replace the UPDATE in update-user.php with an upsert:
$stmt = $pdo->prepare("
    INSERT INTO Profiles (userid, displayname)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE displayname = VALUES(displayname)
");
$stmt->execute([$userid, $displayname]);

if (!empty($password) && $password !== "*********") {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE Users SET passwordhash = ? WHERE userid = ?");
    $stmt->execute([$hashedPassword, $userid]);
}

// Redirect back
header("Location: profile.php");
exit;
?>