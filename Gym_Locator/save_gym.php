<?php
session_start();
require __DIR__ . "/api/bootstrap.php"; 

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$userid = (int) $_SESSION["user_id"]; 

$data = json_decode(file_get_contents('php://input'), true);
$gymName = trim($data['gym'] ?? '');

if (empty($gymName)) {
    echo json_encode(['success' => false, 'message' => 'No gym name provided']);
    exit;
}

require __DIR__ . "/api/mysql.php";
$pdo = mysql_pdo();

$stmt = $pdo->prepare("UPDATE Profiles SET gym = :gym WHERE userid = :userid");
$stmt->execute([':gym' => $gymName, ':userid' => $userid]);

echo json_encode(['success' => true, 'message' => 'Gym saved!']);
?>