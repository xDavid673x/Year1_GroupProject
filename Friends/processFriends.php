<?php
include __DIR__ . "/../DatabaseInit.php";
session_start();

if (empty($_SESSION['user_id'])) {
    header("Location: ../Login_FAQs/login.html");
    exit;
}

$currentUserId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!empty($_POST['username'])) {
        $username = trim($_POST['username']);

        $sql = "SELECT userid FROM Users WHERE username = :username LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $targetId = (int) $user['userid'];

            if ($targetId !== $currentUserId) {
                $checkSql = "SELECT 1
                             FROM Friends
                             WHERE (userA = :currentUser AND userB = :targetUser)
                                OR (userA = :targetUser AND userB = :currentUser)
                             LIMIT 1";
                $checkStmt = $pdo->prepare($checkSql);
                $checkStmt->execute([
                    ':currentUser' => $currentUserId,
                    ':targetUser' => $targetId
                ]);

                $alreadyExists = $checkStmt->fetch();

                if (!$alreadyExists) {
                    $sql = "INSERT INTO Friends (userA, userB, friendstatus)
                            VALUES (:userA, :userB, 'pending')";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        ':userA' => $currentUserId,
                        ':userB' => $targetId
                    ]);
                }
            }
        }
    }

    if (isset($_POST['action']) && $_POST['action'] === 'accept' && !empty($_POST['userid'])) {
        $senderId = (int) $_POST['userid'];

        $sql = "UPDATE Friends
                SET friendstatus = 'friends'
                WHERE userA = :sender
                  AND userB = :currentUser";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':sender' => $senderId,
            ':currentUser' => $currentUserId
        ]);
    }

    if (isset($_POST['action']) && $_POST['action'] === 'reject' && !empty($_POST['userid'])) {
        $senderId = (int) $_POST['userid'];

        $sql = "DELETE FROM Friends
                WHERE userA = :sender
                  AND userB = :currentUser";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':sender' => $senderId,
            ':currentUser' => $currentUserId
        ]);
    }
}

header("Location: friends.php");
exit;
?>