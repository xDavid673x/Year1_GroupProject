<?php
include __DIR__ . "/../DatabaseInit.php";

try {
    // Insert user
    $pdo->exec("INSERT INTO Users(userid, username, passwordhash, email, PhoneNum)
                VALUES (1, 'testuser', 'passwordhash', 'test@example.com', '070000000')
                ON DUPLICATE KEY UPDATE username=username");

    // Insert profile
    $pdo->exec("INSERT INTO Profiles(userid, displayname, height, weight, age, gym, BMI, bio)
                VALUES (1, 'Test User', 175, 70, 21, 'The Gym Group', 22.86, 'This is a test bio')
                ON DUPLICATE KEY UPDATE displayname=displayname");

    echo "Test user inserted!";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>