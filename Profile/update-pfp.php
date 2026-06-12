<?php
session_start();
require __DIR__ . "/../DatabaseInit.php";

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

$userid = $_SESSION['user_id'];
$response = ['success' => true];

if (isset($_FILES['profilepic']) && $_FILES['profilepic']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . "/uploads/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileTmpPath = $_FILES['profilepic']['tmp_name'];
    $fileName    = basename($_FILES['profilepic']['name']);
    $fileExt     = pathinfo($fileName, PATHINFO_EXTENSION);
    $newFileName = "profile_" . $userid . "." . $fileExt;
    $destPath    = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmpPath, $destPath)) {
        $profilepicURL = "uploads/" . $newFileName;  // this is what you store in DB
        $stmt = $pdo->prepare("UPDATE Profiles SET profilepicURL=? WHERE userid=?");
        $stmt->execute([$profilepicURL, $userid]);
        echo json_encode(['success' => true, 'profilepicURL' => $profilepicURL]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
    }
} else {
    $response['success'] = false;
    $response['error'] = 'No file uploaded or upload error';
}

echo json_encode($response);