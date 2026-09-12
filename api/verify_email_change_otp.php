<?php
/**
 * Verify Email-Change OTP
 * Confirms the 6-digit code for a pending email change. On success the
 * challenge is consumed; the frontend then completes the normal profile
 * save which writes the already-verified new address.
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
    
    $challenge_result = execute_sql($conn, "SELECT otp_code, new_email, expires_at FROM email_change_otp WHERE user_id = ?", [$user_id]);
    if ($GLOBALS['use_postgres']) {
        $challenge = pg_fetch_assoc($challenge_result);
    } else {
        $challenge = $challenge_result ? $challenge_result->fetchArray(SQLITE3_ASSOC) : false;
    }
    
    if (!$challenge) {
        close_connection($conn);
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No pending email change. Request a new code from your profile.']);
        ob_end_flush();
        exit;
    }
    
    $expires_ts = strtotime($challenge['expires_at'] ?? '');
    if (!$expires_ts || time() > $expires_ts || !hash_equals((string)$challenge['otp_code'], $code)) {
        if ($expires_ts && time() > $expires_ts) {
            // Expired challenges are consumed
            execute_sql($conn, "DELETE FROM email_change_otp WHERE user_id = ?", [$user_id]);
        }
        close_connection($conn);
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid or expired verification code.']);
        ob_end_flush();
        exit;
    }
    
    // Re-check uniqueness at confirm time (another account may have taken it)
    $taken_result = execute_sql($conn, "SELECT id FROM users WHERE email = ? AND id <> ?", [$challenge['new_email'], $user_id]);
    $taken = false;
    if ($GLOBALS['use_postgres']) {
        $taken = pg_fetch_assoc($taken_result) !== false;
    } else {
        $taken = $taken_result && $taken_result->fetchArray(SQLITE3_ASSOC) !== false;
    }
    if ($taken) {
        execute_sql($conn, "DELETE FROM email_change_otp WHERE user_id = ?", [$user_id]);
        close_connection($conn);
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email already exists.', 'field' => 'profileEmail']);
        ob_end_flush();
        exit;
    }
    
    // Consume the challenge - the frontend save writes the verified address
    execute_sql($conn, "DELETE FROM email_change_otp WHERE user_id = ?", [$user_id]);
    
    close_connection($conn);
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Email verified.']);
    ob_end_flush();
    exit;
} catch (Exception $e) {
    error_log("Error verifying email-change OTP: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    ob_end_flush();
    exit;
}
?>
