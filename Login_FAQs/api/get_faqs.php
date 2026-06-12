<?php
declare(strict_types=1);

require __DIR__ . "/bootstrap.php";
require __DIR__ . "/mysql.php";

require_method("GET");

try {
    $pdo = mysql_pdo();
    
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'FAQs'");
    if ($stmt->rowCount() == 0) {
        json_response(["ok" => true, "faqs" => []]);
    }
    
    $stmt = $pdo->query("SELECT faq_id, question, answer FROM FAQs ORDER BY created_at ASC");
    $faqs = $stmt->fetchAll();
    
    json_response([
        "ok" => true,
        "faqs" => $faqs
    ]);
} catch (Exception $e) {
    json_response(["error" => "Failed to fetch FAQs"], 500);
}
