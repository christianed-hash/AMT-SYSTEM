<?php
// Public endpoint — no login required
// Returns borrowed quantities per tool for the current school year
// Used by request.html to show accurate availability to students

require_once 'db.php';

header('Content-Type: application/json');

$sy_result = $conn->query("SELECT id FROM school_years WHERE is_current = 1 LIMIT 1");
$sy_row    = $sy_result ? $sy_result->fetch_assoc() : null;

if (!$sy_row) {
    echo json_encode(['ok' => true, 'borrowed' => []]);
    exit;
}

$sy_id  = $sy_row['id'];
$result = $conn->query(
    "SELECT tool_name, SUM(quantity) as total_borrowed
     FROM borrow_records
     WHERE school_year_id = $sy_id AND returned = 0
     GROUP BY tool_name"
);

$borrowed = [];
while ($row = $result->fetch_assoc()) {
    $borrowed[$row['tool_name']] = (int)$row['total_borrowed'];
}

echo json_encode(['ok' => true, 'borrowed' => $borrowed]);
