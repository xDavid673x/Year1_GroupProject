<?php
include __DIR__ . "/../DatabaseInit.php";
session_start();

if (empty($_SESSION['user_id'])) {
    header("Location: ../Login_FAQs/login.html");
    exit;
}

$workoutID = $_GET['id'] ?? null;

if ($workoutID) {
    $sql = "DELETE FROM Workouts
            WHERE workoutID = :wid AND userid = :uid";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'wid' => $workoutID,
        'uid' => $_SESSION['user_id']
    ]);
}

header('Location: viewWorkouts.php');
exit;
?>