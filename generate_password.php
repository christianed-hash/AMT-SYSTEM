<?php
// ============================================================
//  Password Hash Generator
//  Open: http://localhost/PROJECT%20AMT/api/generate_password.php
//  DELETE this file after use!
// ============================================================
$password = $_GET['p'] ?? '';
if ($password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    echo "<p><strong>Password:</strong> " . htmlspecialchars($password) . "</p>";
    echo "<p><strong>Hash:</strong> <code>$hash</code></p>";
    echo "<p style='color:red'>Copy the hash above → paste into phpMyAdmin admins table → DELETE this file!</p>";
} else {
    echo '<form>
        <label>Enter password to hash:</label><br><br>
        <input type="text" name="p" style="padding:8px;width:300px;font-size:14px">
        <button type="submit" style="padding:8px 16px;font-size:14px">Generate Hash</button>
    </form>';
}
