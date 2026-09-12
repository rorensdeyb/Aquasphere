<?php
/**
 * Send Email-Change OTP
 * Sends a 6-digit verification code to the user's NEW email address.
 * Called with {"new_email": "..."} for a fresh request, or with an empty
 * body to resend using the pending challenge (60s cooldown enforced).
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
$new_email_input = trim($input['new_email'] ?? '');

try {
    require_once 'database.php';
    require_once 'sanitize.php';
    require_once 'email_service.php';
    
    init_db();
    $conn = get_db_connection();
    $user_id = $_SESSION['user_id'];
    
    // Load current user for comparison + display name
    $user_result = execute_sql($conn, "SELECT username, email FROM users WHERE id = ?", [$user_id]);
    if ($GLOBALS['use_postgres']) {
        $current_user = pg_fetch_assoc($user_result);
    } else {
        $current_user = $user_result ? $user_result->fetchArray(SQLITE3_ASSOC) : false;
    }
    if (!$current_user) {
        close_connection($conn);
        ob_clean();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        ob_end_flush();
        exit;
    }
    
    if ($new_email_input !== '') {
        // Fresh request: validate the new address
        $new_email = sanitize_email($new_email_input, 128);
        if (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            close_connection($conn);
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.', 'field' => 'profileEmail']);
            ob_end_flush();
            exit;
        }
        if (strtolower($new_email) === strtolower($current_user['email'] ?? '')) {
            close_connection($conn);
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email address is unchanged.', 'field' => 'profileEmail']);
            ob_end_flush();
            exit;
        }
        // Reject addresses taken by another account
        $taken_result = execute_sql($conn, "SELECT id FROM users WHERE email = ? AND id <> ?", [$new_email, $user_id]);
        $taken = false;
        if ($GLOBALS['use_postgres']) {
            $taken = pg_fetch_assoc($taken_result) !== false;
        } else {
            $taken = $taken_result && $taken_result->fetchArray(SQLITE3_ASSOC) !== false;
        }
        if ($taken) {
            close_connection($conn);
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email already exists.', 'field' => 'profileEmail']);
            ob_end_flush();
            exit;
        }
    } else {
        // Resend mode: reuse the pending challenge address
        $challenge_result = execute_sql($conn, "SELECT new_email, created_at FROM email_change_otp WHERE user_id = ?", [$user_id]);
        if ($GLOBALS['use_postgres']) {
            $challenge = pg_fetch_assoc($challenge_result);
        } else {
            $challenge = $challenge_result ? $challenge_result->fetchArray(SQLITE3_ASSOC) : false;
        }
        if (!$challenge) {
            close_connection($conn);
            ob_clean();
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'No pending email change. Save your profile again.']);
            ob_end_flush();
            exit;
        }
        // 60s resend cooldown
        $created_ts = strtotime($challenge['created_at'] ?? '');
        if ($created_ts && (time() - $created_ts) < 60) {
            close_connection($conn);
            ob_clean();
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Please wait before requesting a new code.', 'retryAfterSeconds' => 60 - (time() - $created_ts)]);
            ob_end_flush();
            exit;
        }
        $new_email = $challenge['new_email'];
    }
    
    // Generate code (10 minute expiry) and upsert the challenge
    $otp_code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $now = date('Y-m-d H:i:s');
    $expires_at = date('Y-m-d H:i:s', time() + 600);
    
    if ($GLOBALS['use_postgres']) {
        $upsert = "INSERT INTO email_change_otp (user_id, otp_code, new_email, created_at, expires_at) VALUES (?, ?, ?, ?, ?)
                   ON CONFLICT (user_id) DO UPDATE SET otp_code = EXCLUDED.otp_code, new_email = EXCLUDED.new_email, created_at = EXCLUDED.created_at, expires_at = EXCLUDED.expires_at";
    } else {
        $upsert = "INSERT OR REPLACE INTO email_change_otp (user_id, otp_code, new_email, created_at, expires_at) VALUES (?, ?, ?, ?, ?)";
    }
    $upsert_result = execute_sql($conn, $upsert, [$user_id, $otp_code, $new_email, $now, $expires_at]);
    if ($upsert_result === false) {
        close_connection($conn);
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not start email verification. Please try again.']);
        ob_end_flush();
        exit;
    }
    
    // Send the code to the NEW address
    $email_sent = send_email_change_otp_email_brevo($new_email, $otp_code, $current_user['username'] ?? 'there');
    if (!$email_sent && get_brevo_service() !== null) {
        // Configured service but delivery failed - drop the challenge
        execute_sql($conn, "DELETE FROM email_change_otp WHERE user_id = ?", [$user_id]);
        close_connection($conn);
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not send verification email. Please try again.']);
        ob_end_flush();
        exit;
    }
    
    close_connection($conn);
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'We sent a 6-digit code to your new email address. Enter it to save your profile.']);
    ob_end_flush();
    exit;
} catch (Exception $e) {
    error_log("Error sending email-change OTP: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    ob_end_flush();
    exit;
}
?>
