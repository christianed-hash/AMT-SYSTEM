<?php
require_once 'db.php';
requireLogin();

// GET — list all records for current SY
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sy_result = $conn->query("SELECT id FROM school_years WHERE is_current = 1 LIMIT 1");
    $sy_row    = $sy_result->fetch_assoc();

    if (!$sy_row) {
        echo json_encode(['ok' => true, 'records' => []]);
        exit;
    }

    $sy_id  = $sy_row['id'];
    $result = $conn->query(
        "SELECT id, student_name, student_id, year_level, section, semester, tool_name, quantity, date_out, due_date, returned
         FROM borrow_records
         WHERE school_year_id = $sy_id
         ORDER BY created_at DESC"
    );

    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = formatRecord($row);
    }

    echo json_encode(['ok' => true, 'records' => $records]);
    exit;
}

// POST — add a new borrow record
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    $sy_result = $conn->query("SELECT id FROM school_years WHERE is_current = 1 LIMIT 1");
    $sy_row    = $sy_result->fetch_assoc();

    if (!$sy_row) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'No active school year.']);
        exit;
    }

    $sy_id       = $sy_row['id'];
    $studentName = $data['studentName'] ?? '';
    $studentId   = $data['studentId']   ?? '';
    $yearLevel   = $data['yearLevel']   ?? '';
    $section     = $data['section']     ?? '';
    $semester    = $data['semester']    ?? '1st Semester';
    $toolName    = $data['toolName']    ?? '';
    $dateOut     = $data['dateOut']     ?? date('Y-m-d H:i:s');

    if (!in_array($semester, ['1st Semester', '2nd Semester'])) {
        $semester = '1st Semester';
    }

    $stmt = $conn->prepare(
        "INSERT INTO borrow_records (school_year_id, student_name, student_id, year_level, section, semester, tool_name, date_out, returned)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)"
    );
    $stmt->bind_param('isssssss', $sy_id, $studentName, $studentId, $yearLevel, $section, $semester, $toolName, $dateOut);
    $stmt->execute();

    $new_id = $conn->insert_id;
    $row    = $conn->query("SELECT * FROM borrow_records WHERE id = $new_id")->fetch_assoc();

    echo json_encode(['ok' => true, 'record' => formatRecord($row)]);
    exit;
}

function formatRecord($row) {
    return [
        'id'          => (int)$row['id'],
        'studentName' => $row['student_name'],
        'studentId'   => $row['student_id'],
        'yearLevel'   => $row['year_level'] ?? '',
        'section'     => $row['section'],
        'semester'    => $row['semester'] ?? '1st Semester',
        'toolName'    => $row['tool_name'],
        'quantity'    => (int)($row['quantity'] ?? 1),
        'dateOut'     => $row['date_out'],
        'dueDate'     => $row['due_date'] ?? null,
        'returned'    => (bool)$row['returned'],
    ];
}
