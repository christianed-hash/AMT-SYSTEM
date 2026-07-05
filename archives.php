<?php
require_once 'db.php';
requireLogin();

// GET /api/archives.php — list all archived school years
// GET /api/archives.php?sy=2024-2025 — get records for a specific SY

$sy_label = $_GET['sy'] ?? null;

if ($sy_label) {
    // Records for a specific archived SY
    $stmt = $conn->prepare(
        "SELECT br.id, br.student_name, br.student_id, br.year_level, br.section, br.semester, br.tool_name, br.date_out, br.returned
         FROM borrow_records br
         JOIN school_years sy ON br.school_year_id = sy.id
         WHERE sy.label = ?
         ORDER BY br.semester ASC, br.created_at DESC"
    );
    $stmt->bind_param('s', $sy_label);
    $stmt->execute();
    $result  = $stmt->get_result();
    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = [
            'id'          => (int)$row['id'],
            'studentName' => $row['student_name'],
            'studentId'   => $row['student_id'],
            'yearLevel'   => $row['year_level'],
            'section'     => $row['section'],
            'semester'    => $row['semester'] ?: '1st Semester',
            'toolName'    => $row['tool_name'],
            'dateOut'     => $row['date_out'],
            'returned'    => (bool)$row['returned'],
        ];
    }
    echo json_encode(['ok' => true, 'records' => $records]);
} else {
    // List all archived (non-current) school years
    $result   = $conn->query(
        "SELECT sy.label, COUNT(br.id) as record_count
         FROM school_years sy
         LEFT JOIN borrow_records br ON br.school_year_id = sy.id
         WHERE sy.is_current = 0
         GROUP BY sy.id
         ORDER BY sy.label DESC"
    );
    $archives = [];
    while ($row = $result->fetch_assoc()) {
        $archives[] = ['sy' => $row['label'], 'count' => (int)$row['record_count']];
    }
    echo json_encode(['ok' => true, 'archives' => $archives]);
}
