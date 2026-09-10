<?php
/**
 * Suspend/Unsuspend User API
 * Admin-only endpoint to suspend an account with a reason, or lift suspension.
 */

// Buffer all output so no PHP warnings/notices leak before JSON
ob_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
ini_set('display_errors', '0');

// Helper: discard any buffered output, echo clean JSON, and exit
function json_exit($data, $status_code = null) {
    ob_end_clean();
    if ($status_code !== null) {
        http_response_code($status_code);
    }
    echo json_encode($data);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../database.php';
require_once '../sanitize.php';
require_once '../email_service.php';

// Admin check
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    json_exit(['success' => false, 'message' => 'Access denied. Admin only.'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_exit(['success' => false, 'message' => 'Method not allowed'], 405);
}

$input = sanitize_array_recursive(json_decode(file_get_contents('php://input'), true));
$user_id = sanitize_int($input['user_id'] ?? 0);
$action = sanitize_string($input['action'] ?? '', 32);
$reason = sanitize_string($input['reason'] ?? '', 1024);

if ($user_id <= 0) {
    json_exit(['success' => false, 'message' => 'Valid user_id is required']);
}

if (!in_array($action, ['suspend', 'unsuspend'], true)) {
    json_exit(['success' => false, 'message' => 'Invalid action']);
}

if ($action === 'suspend' && $reason === '') {
    json_exit(['success' => false, 'message' => 'Suspension reason is required']);
}

// Prevent suspending yourself
if ($user_id == ($_SESSION['user_id'] ?? -1)) {
    json_exit(['success' => false, 'message' => 'You cannot change suspension on your own account']);
}

init_db();
$conn = get_db_connection();

// Fetch user
$query = "SELECT id, username, email, is_admin, suspended FROM users WHERE id = ?";
$result = execute_sql($conn, $query, [$user_id]);

if ($GLOBALS['use_postgres']) {
    $user = pg_fetch_assoc($result);
} else {
    $user = $result->fetchArray(SQLITE3_ASSOC);
}

if (!$user) {
    close_connection($conn);
    json_exit(['success' => false, 'message' => 'User not found']);
}

// Do not allow suspending admins
if (intval($user['is_admin']) === 1) {
    close_connection($conn);
    json_exit(['success' => false, 'message' => 'Cannot suspend admin users']);
}

if ($action === 'suspend') {
    $update = "UPDATE users SET suspended = 1, suspension_reason = ?, suspended_at = CURRENT_TIMESTAMP, suspension_lifted_at = NULL WHERE id = ?";
    $ok = execute_sql($conn, $update, [$reason, $user_id]);
    close_connection($conn);

    if ($ok === false) {
        json_exit(['success' => false, 'message' => 'Failed to suspend user']);
    }

    // Notify user via email (best-effort)
    try {
        send_suspension_email_brevo($user['email'], $user['username'], $reason);
    } catch (Exception $e) {
        error_log("Suspension email failed: " . $e->getMessage());
    }

    json_exit(['success' => true, 'message' => 'User suspended successfully']);
}

// Unsuspend
$update = "UPDATE users SET suspended = 0, suspension_reason = NULL, suspension_lifted_at = CURRENT_TIMESTAMP WHERE id = ?";
$ok = execute_sql($conn, $update, [$user_id]);
close_connection($conn);

if ($ok === false) {
    json_exit(['success' => false, 'message' => 'Failed to lift suspension']);
}

// Notify user via email (best-effort)
try {
    send_unsuspension_email_brevo($user['email'], $user['username']);
} catch (Exception $e) {
    error_log("Unsuspension email failed: " . $e->getMessage());
}

json_exit(['success' => true, 'message' => 'Suspension lifted successfully']);
