<?php
declare(strict_types=1);

require __DIR__ . "/bootstrap.php";
require __DIR__ . "/mysql.php";

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    json_response(["error" => "Unauthorized"], 403);
}

require_method("POST");
$data = get_request_data();

try {
    $pdo = mysql_pdo();
    
    if ($data['action'] === 'add') {
        $q = trim($data['question'] ?? '');
        $a = trim($data['answer'] ?? '');
        if (!$q || !$a) json_response(["error" => "Question and answer required"], 400);
        
        $stmt = $pdo->prepare("INSERT INTO FAQs (question, answer) VALUES (?, ?)");
        $stmt->execute([$q, $a]);
        json_response(["ok" => true]);
        
    } elseif ($data['action'] === 'delete') {
        $id = (int) ($data['id'] ?? 0);
        if (!$id) json_response(["error" => "ID required"], 400);
        
        $stmt = $pdo->prepare("DELETE FROM FAQs WHERE faq_id = ?");
        $stmt->execute([$id]);
        json_response(["ok" => true]);
    } else {
        json_response(["error" => "Invalid action"], 400);
    }
} catch (Exception $e) {
    json_response(["error" => "Database error"], 500);
}
