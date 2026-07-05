<?php
require_once 'db.php';
requireLogin();

echo json_encode([
    'ok'       => true,
    'username' => $_SESSION['admin_username'],
    'email'    => $_SESSION['admin_email'],
]);
