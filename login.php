<?php
require_once 'db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'POST required.']);
    exit;
}
// ── Rate limiting ──
$ip       = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ip_key   = 'login_attempts_' . md5($ip);
$attempts = (int)($_SESSION[$ip_key . '_count'] ?? 0);
$last_try = (int)($_SESSION[$ip_key . '_time']  ?? 0);
if (time() - $last_try > 900) {
    $attempts = 0;
}
if ($attempts >= 5) {
    $wait = 900 - (time() - $last_try);
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => "Too many failed attempts. Try again in " . ceil($wait/60) . " minute(s)."]);
    exit;
}
// ── Validate input ──
$data     = json_decode(file_get_contents('php://input'), true);
$username = trim($data['username'] ?? '');
$password = $data['password'] ?? '';
if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Username and password required.']);
    exit;
}
// ── Check credentials ──
$stmt = $conn->prepare('SELECT id, username, email, password_hash FROM admins WHERE username = ? OR email = ? LIMIT 1');
$stmt->bind_param('ss', $username, $username);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
if ($admin && password_verify($password, $admin['password_hash'])) {
    unset($_SESSION[$ip_key . '_count'], $_SESSION[$ip_key . '_time']);
    // ← removed session_regenerate_id (causes 500 on some shared hosts)
    $_SESSION['admin_id']       = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_email']    = $admin['email'];
    echo json_encode(['ok' => true, 'username' => $admin['username'], 'email' => $admin['email']]);
} else {
    $_SESSION[$ip_key . '_count'] = $attempts + 1;
    $_SESSION[$ip_key . '_time']  = time();
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Incorrect username or password.']);
}