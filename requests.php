<?php
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => "PHP Error [$errno]: $errstr in $errfile on line $errline"]);
    exit;
});
set_exception_handler(function($e) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine()]);
    exit;
});

require_once 'db.php';
require_once 'mailer.php';

// GET — list all pending requests (admin only) OR check status by ID (student)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Student checking their request status by ID
    if (isset($_GET['id'])) {
        $id   = (int)$_GET['id'];

        // Check if responded_at column exists
        $colCheck       = $conn->query("SHOW COLUMNS FROM borrow_requests LIKE 'responded_at'");
        $hasRespondedAt = $colCheck && $colCheck->num_rows > 0;
        $respondedCol   = $hasRespondedAt ? ', br.responded_at' : '';

        // Also fetch due_date from borrow_records (set by admin on approval)
        $stmt = $conn->prepare(
            "SELECT br.status, br.tool_name, br.quantity{$respondedCol},
                    rec.due_date
             FROM borrow_requests br
             LEFT JOIN borrow_records rec
               ON rec.student_id = br.student_id AND rec.tool_name = br.tool_name
              AND rec.date_out >= br.created_at
             WHERE br.id = ?
             ORDER BY rec.date_out DESC
             LIMIT 1"
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            echo json_encode([
                'ok'           => true,
                'status'       => $row['status'],
                'tool'         => $row['tool_name'],
                'quantity'     => (int)$row['quantity'],
                'responded_at' => $row['responded_at'] ?? null,
                'due_date'     => $row['due_date'] ?? null,
            ]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Request not found.']);
        }
        exit;
    }

    // Admin listing pending requests
    requireLogin();
    $status = $_GET['status'] ?? 'pending';
    $stmt = $conn->prepare(
        "SELECT id, student_name, student_email, student_id, year_level, section, semester, tool_name, quantity, status, created_at
         FROM borrow_requests
         WHERE status = ?
         ORDER BY created_at DESC"
    );
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $result = $stmt->get_result();
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    echo json_encode(['ok' => true, 'requests' => $requests]);
    exit;
}

// POST — student submits a borrow request (no login needed)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data        = json_decode(file_get_contents('php://input'), true);
    $name        = trim($data['studentName']  ?? '');
    $email       = trim($data['studentEmail'] ?? '');
    $student_id  = trim($data['studentId']    ?? '');
    $year_level  = trim($data['yearLevel']    ?? '');
    $section     = trim($data['section']      ?? '');
    $tool        = trim($data['toolName']     ?? '');
    $quantity    = max(1, (int)($data['quantity'] ?? 1));

    // Auto-detect semester from server date: Aug–Dec = 1st Semester, Jan–Jul = 2nd Semester
    $month    = (int)date('n');
    $semester = $month >= 8 ? '1st Semester' : '2nd Semester';

    if (!$name || !$email || !$student_id || !$tool) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'All fields are required.']);
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO borrow_requests (student_name, student_email, student_id, year_level, section, semester, tool_name, quantity, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
    );
    $stmt->bind_param('sssssssi', $name, $email, $student_id, $year_level, $section, $semester, $tool, $quantity);
    $stmt->execute();

    echo json_encode(['ok' => true, 'message' => 'Request submitted! Please wait for admin approval.', 'requestId' => $conn->insert_id]);
    exit;
}

// PATCH — admin accepts or rejects a request
if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    requireLogin();
    $data      = json_decode(file_get_contents('php://input'), true);
    $id        = (int)($data['id']     ?? 0);
    $newStatus = $data['status'] ?? '';

    // Optional: batch info — when provided, email is sent only on the last item
    $batchIds   = isset($data['batchIds'])   ? array_map('intval', (array)$data['batchIds']) : null;
    $isLastItem = isset($data['isLastItem']) ? (bool)$data['isLastItem'] : true;

    if (!$id || !in_array($newStatus, ['approved', 'rejected'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid request.']);
        exit;
    }

    // Update request status and stamp the response time (responded_at may not exist yet — safe fallback)
    $hasRespondedAt = false;
    $colCheck = $conn->query("SHOW COLUMNS FROM borrow_requests LIKE 'responded_at'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $hasRespondedAt = true;
    }

    if ($hasRespondedAt) {
        $stmtUpdate = $conn->prepare("UPDATE borrow_requests SET status = ?, responded_at = NOW() WHERE id = ?");
    } else {
        $stmtUpdate = $conn->prepare("UPDATE borrow_requests SET status = ? WHERE id = ?");
    }
    $stmtUpdate->bind_param('si', $newStatus, $id);
    $stmtUpdate->execute();

    // If approved, create a borrow record automatically
    if ($newStatus === 'approved') {
        $due_date        = !empty($data['due_date']) ? $data['due_date'] : null;
        $approved_qty    = max(1, (int)($data['approved_qty'] ?? 1));

        $req = $conn->query("SELECT * FROM borrow_requests WHERE id = $id")->fetch_assoc();
        $sy  = $conn->query("SELECT id FROM school_years WHERE is_current = 1 LIMIT 1")->fetch_assoc();

        if ($req && $sy) {
            $sy_id      = $sy['id'];
            $name       = $req['student_name'];
            $student_id = $req['student_id'];
            $year_level = $req['year_level'] ?? '';
            $section    = $req['section'] ?? '';
            $semester   = !empty($req['semester']) ? $req['semester'] : '1st Semester';
            if (!in_array($semester, ['1st Semester', '2nd Semester'])) $semester = '1st Semester';
            $tool       = $req['tool_name'];
            $date_out   = date('Y-m-d H:i:s');

            $stmt2 = $conn->prepare(
                "INSERT INTO borrow_records (school_year_id, student_name, student_id, year_level, section, semester, tool_name, quantity, date_out, due_date, returned)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)"
            );
            $stmt2->bind_param('issssssiss', $sy_id, $name, $student_id, $year_level, $section, $semester, $tool, $approved_qty, $date_out, $due_date);
            $stmt2->execute();
        }
    }

    // Send combined email only on the last item of a batch (or always if no batch info)
    if ($isLastItem && $batchIds) {
        // Fetch all requests in this batch to build a grouped email
        $placeholders = implode(',', array_fill(0, count($batchIds), '?'));
        $types        = str_repeat('i', count($batchIds));
        $stmtBatch    = $conn->prepare(
            "SELECT br.student_name, br.student_email, br.tool_name, br.quantity, br.status,
                    rec.due_date, rec.quantity AS approved_qty
             FROM borrow_requests br
             LEFT JOIN borrow_records rec
               ON rec.student_id = br.student_id AND rec.tool_name = br.tool_name
              AND rec.date_out >= br.created_at
             WHERE br.id IN ($placeholders)
             ORDER BY rec.date_out DESC"
        );
        $stmtBatch->bind_param($types, ...$batchIds);
        $stmtBatch->execute();
        $batchRows = $stmtBatch->get_result()->fetch_all(MYSQLI_ASSOC);

        if ($batchRows) {
            // Deduplicate — keep latest record per tool
            $seen  = [];
            $items = [];
            foreach ($batchRows as $row) {
                $key = $row['tool_name'];
                if (!isset($seen[$key])) {
                    $seen[$key] = true;
                    $items[]    = $row;
                }
            }

            $first = $items[0];
            sendBatchNotification(
                $first['student_email'],
                $first['student_name'],
                $items,
                $newStatus   // overall action (approved / rejected)
            );
        }
    } elseif ($isLastItem) {
        // Single tool — send the regular single-tool email
        $reqForEmail = $conn->query("SELECT student_name, student_email, tool_name, quantity FROM borrow_requests WHERE id = $id")->fetch_assoc();
        if ($reqForEmail && !empty($reqForEmail['student_email'])) {
            $emailQty     = ($newStatus === 'approved' && isset($approved_qty)) ? $approved_qty : ($reqForEmail['quantity'] ?? 1);
            $emailDueDate = ($newStatus === 'approved' && !empty($due_date)) ? $due_date : null;
            sendRequestNotification(
                $reqForEmail['student_email'],
                $reqForEmail['student_name'],
                $reqForEmail['tool_name'],
                $newStatus,
                $emailQty,
                $emailDueDate
            );
        }
    }

    echo json_encode(['ok' => true]);
    exit;
}