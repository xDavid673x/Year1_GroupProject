<?php
declare(strict_types=1);

require __DIR__ . "/bootstrap.php";
require __DIR__ . "/mysql.php";

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    json_response(["error" => "Unauthorized"], 403);
}

require_method("GET");

try {
    $pdo = mysql_pdo();
    
    // Nodes: Users and their display names
    $stmt = $pdo->query("SELECT u.userid AS id, COALESCE(p.displayname, u.username) AS label 
                         FROM Users u 
                         LEFT JOIN Profiles p ON u.userid = p.userid");
    $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Ensure IDs are integers so Vis.js handles them cleanly
    foreach ($nodes as &$node) {
        $node['id'] = (int) $node['id'];
        $node['title'] = "User ID: " . $node['id']; // simple tooltip
    }
    unset($node);
    
    // Edges: active friendships
    $stmt = $pdo->query("SELECT userA AS `from`, userB AS `to` 
                         FROM Friends 
                         WHERE friendstatus = 'friends'");
    $edges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($edges as &$edge) {
        $edge['from'] = (int) $edge['from'];
        $edge['to'] = (int) $edge['to'];
    }
    unset($edge);
    
    json_response([
        "ok" => true,
        "nodes" => $nodes,
        "edges" => $edges
    ]);

} catch (Exception $e) {
    json_response(["error" => "Database error: " . $e->getMessage()], 500);
}
