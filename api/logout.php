<?php
/**
 * Logout Handler
 */

session_start();
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['aquasphere_remember'])) {
    setcookie('aquasphere_remember', '', time() - 3600, '/');
}

// If called via fetch/XHR (password-change auto-logout), return JSON
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' || stripos($contentType, 'application/json') !== false || stripos($accept, 'application/json') !== false) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Logged out']);
    exit;
}

// Handle redirect parameter
$redirect = $_GET['redirect'] ?? 'login.html';
if ($redirect === 'index.html') {
    header('Location: ../index.html');
} else {
    header('Location: ../login.html');
}
exit;
?>

