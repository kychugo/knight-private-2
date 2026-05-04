<?php
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Reject submissions when the platform is locked
try {
    if (getSetting('platform_locked') !== '0') {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Platform is locked']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode(['success' => false, 'error' => 'Service unavailable']);
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
$moves = isset($data['moves']) ? (int)$data['moves'] : -1;

if ($name === '') {
    $name = '玩家';
}
if ($moves < 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid moves value']);
    exit;
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
