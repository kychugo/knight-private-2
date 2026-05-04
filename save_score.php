<?php
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

$name  = isset($data['name'])  ? trim(mb_substr($data['name'], 0, 100)) : '';
$moves = isset($data['moves']) ? (int)$data['moves'] : 0;

if ($name === '') {
    $name = '玩家';
}
if ($moves < 0) {
    $moves = 0;
}

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare("INSERT INTO `rankings` (`name`, `moves`) VALUES (?, ?)");
    $stmt->execute([$name, $moves]);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
