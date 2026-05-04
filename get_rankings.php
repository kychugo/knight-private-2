<?php
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo  = getDB();
    $stmt = $pdo->query(
        "SELECT `name`, `moves` FROM `rankings` ORDER BY `moves` DESC LIMIT 5"
    );
    echo json_encode($stmt->fetchAll());
} catch (Exception $e) {
    echo json_encode([]);
}
