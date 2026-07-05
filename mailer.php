<?php
define('MAIL_FROM',     'adminamt4@gmail.com');
define('MAIL_PASSWORD', 'pevmbjvgonkcmyzj');  
define('MAIL_NAME',     'AMT Tool Room');

function sendBatchNotification($toEmail, $toName, $items, $overallStatus) {
    $subject     = 'Tool Request Update — AMT System';
    $allApproved = array_reduce($items, fn($c, $i) => $c && $i['status'] === 'approved', true);
    $allRejected = array_reduce($items, fn($c, $i) => $c && $i['status'] === 'rejected', true);

    if ($allApproved) {
        $headline    = 'ACCEPTED ✅';
        $headColor   = '#16a34a';
        $intro       = 'Your borrow request has been <strong>accepted</strong>. You may now collect the following tools from the tool room:';
    } elseif ($allRejected) {
        $headline    = 'REJECTED ❌';
        $headColor   = '#dc2626';
        $intro       = 'Sorry, your borrow request has been <strong>rejected</strong>. Please try requesting different tools.';
    } else {
        $headline    = 'PARTIALLY PROCESSED ℹ️';
        $headColor   = '#b45309';
        $intro       = 'Your borrow request has been partially processed. See the details below:';
    }

    // Build tool rows
    $toolRows = '';
    foreach ($items as $item) {
        $approved = $item['status'] === 'approved';
        $qty      = $approved && !empty($item['approved_qty']) ? (int)$item['approved_qty'] : (int)($item['quantity'] ?? 1);
        $icon     = $approved ? '✅' : '❌';
        $dueStr   = '';
        if ($approved && !empty($item['due_date'])) {
            $formatted = date('F j, Y g:i A', strtotime($item['due_date']));
            $dueStr    = '<br><span style="font-size:11px;color:#dc2626">📅 Return by: <strong>' . $formatted . '</strong></span>';
        }
        $toolRows .= '
          <tr>
            <td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;font-size:13px;color:#0f172a">
              ' . $icon . ' <strong>' . htmlspecialchars($item['tool_name']) . '</strong>' . $dueStr . '
            </td>
            <td style="padding:8px 10px;border-bottom:1px solid #f1f5f9;font-size:13px;color:#475569;text-align:center">
              × ' . $qty . '
            </td>
          </tr>';
    }

    $body = '
    <div style="font-family:Segoe UI,sans-serif;max-width:520px;margin:0 auto;background:#f8fafc;padding:24px;border-radius:12px">
      <div style="background:#8b0000;padding:16px 20px;border-radius:8px 8px 0 0;text-align:center">
        <h2 style="color:white;margin:0;font-size:18px">AMT Tool Borrowing System</h2>
      </div>
      <div style="background:white;padding:24px;border-radius:0 0 8px 8px;border:1px solid #e2e8f0">
        <p style="color:#0f172a;font-size:15px">Hi <strong>' . htmlspecialchars($toName) . '</strong>,</p>
        <p style="color:#475569;font-size:14px">' . $intro . '</p>
        <div style="margin:16px 0;text-align:center">
          <span style="font-size:20px;font-weight:700;color:' . $headColor . '">' . $headline . '</span>
        </div>
        <table style="width:100%;border-collapse:collapse;background:#f8fafc;border-radius:8px;overflow:hidden;margin-bottom:16px">
          <thead>
            <tr style="background:#f1f5f9">
              <th style="padding:8px 10px;text-align:left;font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase">Tool</th>
              <th style="padding:8px 10px;text-align:center;font-size:11px;color:#64748b;font-weight:600;text-transform:uppercase">Qty</th>
            </tr>
          </thead>
          <tbody>' . $toolRows . '</tbody>
        </table>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:20px 0">
        <p style="color:#94a3b8;font-size:12px;text-align:center">This is an automated message from the AMT Tool Room system.</p>
      </div>
    </div>';

    return sendViaSMTP($toEmail, $toName, $subject, $body);
}

function sendRequestNotification($toEmail, $toName, $toolName, $status, $quantity = 1, $dueDate = null) {
    $subject = 'Tool Request Update — AMT System';
    $qty     = max(1, (int)$quantity);
    $toolDisplay = ($qty > 1 ? $qty . '× ' : '') . htmlspecialchars($toolName);

    if ($status === 'approved') {
        $statusText  = 'ACCEPTED ✅';
        $statusColor = '#16a34a';
        $message     = 'Your request has been <strong>accepted</strong>. You may now collect <strong>' . $toolDisplay . '</strong> from the tool room.';
        // Add return deadline if provided
        $dueLine = '';
        if ($dueDate) {
            $formatted = date('F j, Y g:i A', strtotime($dueDate));
            $dueLine   = '<div style="margin-top:8px;font-size:13px;color:#64748b">Return Deadline: <strong style="color:#dc2626">' . $formatted . '</strong></div>';
        }
    } else {
        $statusText  = 'REJECTED ❌';
        $statusColor = '#dc2626';
        $message     = 'Sorry, your request for <strong>' . $toolDisplay . '</strong> has been <strong>rejected</strong>. Please try requesting a different tool.';
        $dueLine     = '';
    }

    $body = '
    <div style="font-family:Segoe UI,sans-serif;max-width:480px;margin:0 auto;background:#f8fafc;padding:24px;border-radius:12px">
      <div style="background:#8b0000;padding:16px 20px;border-radius:8px 8px 0 0;text-align:center">
        <h2 style="color:white;margin:0;font-size:18px">AMT Tool Borrowing System</h2>
      </div>
      <div style="background:white;padding:24px;border-radius:0 0 8px 8px;border:1px solid #e2e8f0">
        <p style="color:#0f172a;font-size:15px">Hi <strong>' . htmlspecialchars($toName) . '</strong>,</p>
        <p style="color:#475569;font-size:14px">Your borrow request status has been updated:</p>
        <div style="background:#f1f5f9;border-radius:8px;padding:16px;margin:16px 0;text-align:center">
          <div style="font-size:13px;color:#64748b;margin-bottom:6px">Tool Requested</div>
          <div style="font-size:16px;font-weight:700;color:#0f172a">' . htmlspecialchars($toolName) . '</div>
          <div style="font-size:13px;color:#64748b;margin-top:4px">Quantity: <strong style="color:#0f172a">' . $qty . '</strong></div>
          <div style="margin-top:12px;font-size:18px;font-weight:700;color:' . $statusColor . '">' . $statusText . '</div>
          ' . $dueLine . '
        </div>
        <p style="color:#475569;font-size:14px">' . $message . '</p>
        <hr style="border:none;border-top:1px solid #e2e8f0;margin:20px 0">
        <p style="color:#94a3b8;font-size:12px;text-align:center">This is an automated message from the AMT Tool Room system.</p>
      </div>
    </div>';

    $result = sendViaSMTP($toEmail, $toName, $subject, $body);
    return $result;
}

function mailLog($msg) {
    $logFile = __DIR__ . '/mail_error.log';
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . $msg . "\n", FILE_APPEND);
}

function sendViaSMTP($toEmail, $toName, $subject, $htmlBody) {
    $host     = 'ssl://smtp.gmail.com';
    $port     = 465;
    $timeout  = 30;
    $from     = MAIL_FROM;
    $password = MAIL_PASSWORD;
    $fromName = MAIL_NAME;

    mailLog("Attempting to send to: $toEmail");

    // Connect via SSL on port 465
    $conn = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$conn) {
        mailLog("Connection failed: [$errno] $errstr");
        return false;
    }
    mailLog("Connected to $host:$port");

    // Read greeting
    $resp = fgets($conn, 512);
    mailLog("Greeting: " . trim($resp));
    if (substr($resp, 0, 3) !== '220') { fclose($conn); mailLog("Bad greeting"); return false; }

    // EHLO
    fputs($conn, "EHLO localhost\r\n");
    $ehlo = '';
    while ($line = fgets($conn, 512)) {
        $ehlo .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }
    mailLog("EHLO response: " . trim($ehlo));

    // AUTH LOGIN
    fputs($conn, "AUTH LOGIN\r\n");
    $r = fgets($conn, 512);
    mailLog("AUTH LOGIN: " . trim($r));

    fputs($conn, base64_encode($from) . "\r\n");
    $r = fgets($conn, 512);
    mailLog("Username response: " . trim($r));

    fputs($conn, base64_encode($password) . "\r\n");
    $auth = fgets($conn, 512);
    mailLog("Password response: " . trim($auth));

    if (substr($auth, 0, 3) !== '235') {
        fclose($conn);
        mailLog("AUTH FAILED: " . trim($auth));
        return false;
    }
    mailLog("Authenticated successfully");

    // MAIL FROM
    fputs($conn, "MAIL FROM:<$from>\r\n");
    $r = fgets($conn, 512);
    mailLog("MAIL FROM: " . trim($r));

    // RCPT TO
    fputs($conn, "RCPT TO:<$toEmail>\r\n");
    $r = fgets($conn, 512);
    mailLog("RCPT TO: " . trim($r));

    // DATA
    fputs($conn, "DATA\r\n");
    $r = fgets($conn, 512);
    mailLog("DATA: " . trim($r));

    // Headers + body
    $encodedBody = chunk_split(base64_encode($htmlBody));
    $msg  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <$from>\r\n";
    $msg .= "To: =?UTF-8?B?" . base64_encode($toName) . "?= <$toEmail>\r\n";
    $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $msg .= "MIME-Version: 1.0\r\n";
    $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
    $msg .= "Content-Transfer-Encoding: base64\r\n";
    $msg .= "\r\n";
    $msg .= $encodedBody;
    $msg .= "\r\n.\r\n";

    fputs($conn, $msg);
    $sent = fgets($conn, 512);
    mailLog("Send result: " . trim($sent));

    fputs($conn, "QUIT\r\n");
    fclose($conn);

    $success = substr($sent, 0, 3) === '250';
    mailLog($success ? "Email sent successfully" : "Send FAILED");
    return $success;
}
