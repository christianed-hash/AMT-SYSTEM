<?php
require_once 'db.php';
requireLogin();

// GET — return current school year
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $conn->query("SELECT label FROM school_years WHERE is_current = 1 LIMIT 1");
    $row    = $result->fetch_assoc();

    if (!$row) {
        // Auto-create based on current month (June = new SY in PH)
        $month = (int)date('n');
        $year  = (int)date('Y');
        $start = $month >= 6 ? $year : $year - 1;
        $label = "$start-" . ($start + 1);
        $conn->query("INSERT INTO school_years (label, is_current) VALUES ('$label', 1)");
        echo json_encode(['ok' => true, 'sy' => $label]);
    } else {
        echo json_encode(['ok' => true, 'sy' => $row['label']]);
    }
    exit;
}

// POST — archive current SY and start a new one
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data   = json_decode(file_get_contents('php://input'), true);
    $new_sy = trim($data['new_sy'] ?? '');

    if (!$new_sy || !str_contains($new_sy, '-')) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid school year format. Use YYYY-YYYY.']);
        exit;
    }

    // Deactivate current SY
    $conn->query("UPDATE school_years SET is_current = 0 WHERE is_current = 1");

    // Create or activate new SY
    $stmt = $conn->prepare("INSERT INTO school_years (label, is_current) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_current = 1");
    $stmt->bind_param('s', $new_sy);
    $stmt->execute();

    echo json_encode(['ok' => true, 'sy' => $new_sy]);
    exit;
}
