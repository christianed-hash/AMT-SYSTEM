<?php
require_once 'db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id   = (int)($data['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Record ID required.']);
    exit;
}

$stmt = $conn->prepare("UPDATE borrow_records SET returned = 1 WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Record not found.']);
}
