<?php
session_start();
require __DIR__ . "/../DatabaseInit.php";

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

$userid = $_SESSION['user_id'];
$bio = $_POST['bio'] ?? '';

$stmt = $pdo->prepare("UPDATE Profiles SET bio=? WHERE userid=?");
if ($stmt->execute([$bio, $userid])) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database update failed']);
}
?>