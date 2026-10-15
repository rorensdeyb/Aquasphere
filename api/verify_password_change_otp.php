<?php
/**
 * Verify Password-Change OTP (BondNest-style)
 * Confirms the 6-digit code and applies the staged password hash.
 */

ob_start();

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    ob_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    ob_end_flush();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    ob_end_flush();
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$code = trim($input['code'] ?? '');

if (!preg_match('/^\d{6}$/', $code)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Please enter the 6-digit code from your email.']);
    ob_end_flush();
    exit;
}

try {
    require_once 'database.php';

    init_db();
    $conn = get_db_connection();
    $user_id = $_SESSION['user_id'];

    $ch_result = execute_sql($conn, "SELECT otp_code, pending_hash, expires_at FROM password_change_otp WHERE user_id = ?", [$user_id]);
    if ($GLOBALS['use_postgres']) {
        $challenge = pg_fetch_assoc($ch_result);
    } else {
        $challenge = $ch_result ? $ch_result->fetchArray(SQLITE3_ASSOC) : false;
    }

    if (!$challenge) {
        close_connection($conn);
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No pending password change. Submit the form again.']);
        ob_end_flush();
        exit;
    }

    $expires_ts = strtotime($challenge['expires_at'] ?? '');
    if (!$expires_ts || time() > $expires_ts || !hash_equals((string)$challenge['otp_code'], $code)) {
        if ($expires_ts && time() > $expires_ts) {
            execute_sql($conn, "DELETE FROM password_change_otp WHERE user_id = ?", [$user_id]);
        }
        close_connection($conn);
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
        ob_end_flush();
        exit;
    }

    execute_sql($conn, "UPDATE users SET password_hash = ? WHERE id = ?", [$challenge['pending_hash'], $user_id]);
    execute_sql($conn, "DELETE FROM password_change_otp WHERE user_id = ?", [$user_id]);

    close_connection($conn);
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Password updated successfully!']);
    ob_end_flush();
    exit;
} catch (Exception $e) {
    error_log("Error verifying password-change OTP: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    ob_end_flush();
    exit;
}
?>
