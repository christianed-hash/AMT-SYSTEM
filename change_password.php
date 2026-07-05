<?php
require_once 'db.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}

$data         = json_decode(file_get_contents('php://input'), true);
$current_pass = $data['currentPassword'] ?? '';
$new_pass     = $data['newPassword']     ?? '';
$confirm_pass = $data['confirmPassword'] ?? '';

// Validate
if (!$current_pass || !$new_pass || !$confirm_pass) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'All fields are required.']);
    exit;
}

if ($new_pass !== $confirm_pass) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'New passwords do not match.']);
    exit;
}

if (strlen($new_pass) < 8) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'New password must be at least 8 characters.']);
    exit;
}

// Get current admin's password hash
$admin_id = $_SESSION['admin_id'];
$stmt     = $conn->prepare('SELECT password_hash FROM admins WHERE id = ?');
$stmt->bind_param('i', $admin_id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin || !password_verify($current_pass, $admin['password_hash'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Current password is incorrect.']);
    exit;
}

// Update password
$new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
$stmt     = $conn->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
$stmt->bind_param('si', $new_hash, $admin_id);
$stmt->execute();

echo json_encode(['ok' => true, 'message' => 'Password changed successfully.']);
