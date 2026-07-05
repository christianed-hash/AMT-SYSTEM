<?php
// ============================================================
//  One-time password setter — auto-deletes after running
//  Open: http://localhost/PROJECT%20AMT/api/set_password.php
// ============================================================
require_once 'db.php';

$accounts = [
    [
        'username' => 'admin1',
        'password' => 'myamt2025',
    ],
    [
        'username' => 'admin2',
        'password' => 'myamt2025',
    ],
];

echo "<h2 style='font-family:sans-serif'>AMT — Setting Admin Passwords</h2>";

foreach ($accounts as $account) {
    $hash = password_hash($account['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE admins SET password_hash = ? WHERE username = ?");
    $stmt->bind_param('ss', $hash, $account['username']);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "<p style='color:green;font-family:sans-serif'>✅ <strong>{$account['username']}</strong> → password set to: <strong>{$account['password']}</strong></p>";
    } else {
        echo "<p style='color:orange;font-family:sans-serif'>⚠️ <strong>{$account['username']}</strong> not found — make sure setup.sql was run first.</p>";
    }
}

echo "<br><p style='color:red;font-family:sans-serif'><strong>⚠️ Change admin2 password after first login!</strong></p>";

// Auto-delete this file
unlink(__FILE__);
echo "<p style='color:green;font-family:sans-serif'>✅ This file has been auto-deleted for security.</p>";
