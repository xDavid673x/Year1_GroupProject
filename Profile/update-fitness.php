<?php
session_start();
require __DIR__ . "/../DatabaseInit.php";

if (!isset($_SESSION['user_id'])) {
    die("User not logged in");
}

$userid = $_SESSION['user_id'];

$height = ($_POST['height'] ?? '') !== '' ? (int)$_POST['height']   : null;
$weight = ($_POST['weight'] ?? '') !== '' ? (float)$_POST['weight'] : null;
$age    = ($_POST['age']    ?? '') !== '' ? (int)$_POST['age']      : null;
$gym    = $_POST['gym'] ?? '';
$bio    = $_POST['bio'] ?? '';

// Calculate BMI
$bmi = null;
if (!empty($height) && !empty($weight)) {
    $height_m = $height / 100;
    $bmi = $weight / ($height_m * $height_m);
    $bmi = round($bmi, 2);
}

// Update the database
$stmt = $pdo->prepare("
    UPDATE Profiles 
    SET height = ?, weight = ?, age = ?, gym = ?, BMI = ?, bio = ?
    WHERE userid = ?
");

$stmt->execute([
    $height,
    $weight,
    $age,
    $gym,
    $bmi,
    $bio,
    $userid
]);

header("Location: profile.php");
exit;