<?php
/**
 * Send Password-Change OTP (BondNest-style)
 * Step 1: validates current password + new password strength + "different from previous one",
 * stages the new hash + 6-digit OTP, and emails the code to the user's CURRENT email.
 * Also handles resend when body is {"resend": true} with a 60s cooldown.
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
$is_resend = !empty($input['resend']);

try {
    require_once 'database.php';
    require_once 'email_service.php';

    init_db();
    $conn = get_db_connection();
    $user_id = $_SESSION['user_id'];

    // Load current user
    $user_result = execute_sql($conn, "SELECT username, email, password_hash FROM users WHERE id = ?", [$user_id]);
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

    if ($is_resend) {
        // Resend mode: reuse pending challenge
        $ch_result = execute_sql($conn, "SELECT pending_hash, created_at FROM password_change_otp WHERE user_id = ?", [$user_id]);
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
        $created_ts = strtotime($challenge['created_at'] ?? '');
        if ($created_ts && (time() - $created_ts) < 60) {
            close_connection($conn);
            ob_clean();
            http_response_code(429);
            echo json_encode(['success' => false, 'message' => 'Please wait before requesting a new code.', 'retryAfterSeconds' => 60 - (time() - $created_ts)]);
            ob_end_flush();
            exit;
        }

        $otp_code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $now = date('Y-m-d H:i:s');
        $expires_at = date('Y-m-d H:i:s', time() + 600);
        execute_sql($conn, "UPDATE password_change_otp SET otp_code = ?, created_at = ?, expires_at = ? WHERE user_id = ?", [$otp_code, $now, $expires_at, $user_id]);

        $email_sent = send_password_change_otp_email_brevo($current_user['email'], $otp_code, $current_user['username'] ?? 'there');
        if (!$email_sent && get_brevo_service() !== null) {
            execute_sql($conn, "DELETE FROM password_change_otp WHERE user_id = ?", [$user_id]);
            close_connection($conn);
            ob_clean();
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'We could not resend the verification code. Please try again.']);
            ob_end_flush();
            exit;
        }

        close_connection($conn);
        ob_clean();
        echo json_encode(['success' => true, 'message' => 'A new 6-digit verification code has been sent.']);
        ob_end_flush();
        exit;
    }

    $current_password = $input['current_password'] ?? '';
    $new_password = $input['new_password'] ?? '';
    $confirm_password = $input['confirm_password'] ?? '';

    if (empty($current_password)) {
        close_connection($conn);
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is required.', 'field' => 'profilePassword', 'errorKey' => 'incorrect-required']);
        ob_end_flush();
        exit;
    }

    if (!password_verify($current_password, $current_user['password_hash'])) {
        close_connection($conn);
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.', 'field' => 'profilePassword']);
        ob_end_flush();
        exit;
    }

    if (empty($new_password)) {
        close_connection($conn);
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password is required.', 'field' => 'profileNewPassword']);
        ob_end_flush();
        exit;
    }

    $strong = preg_match('/[a-z]/', $new_password) &&
              preg_match('/[A-Z]/', $new_password) &&
              preg_match('/\d/', $new_password) &&
              preg_match('/[ !"#$%&\'()*+,\-\.\/:;<=>?@\[\]^_`{|}~]/', $new_password) &&
              strlen($new_password) >= 8;
    if (!$strong) {
        close_connection($conn);
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Min 8 chars with upper, lower, number, special.', 'field' => 'profileNewPassword']);
        ob_end_flush();
        exit;
    }

    if (password_verify($new_password, $current_user['password_hash'])) {
        close_connection($conn);
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please enter a password different from your previous one.', 'field' => 'profileNewPassword']);
        ob_end_flush();
        exit;
    }

    if (empty($confirm_password)) {
        close_connection($conn);
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password confirmation is required.', 'field' => 'profileConfirmPassword']);
        ob_end_flush();
        exit;
    }

    if ($new_password !== $confirm_password) {
        close_connection($conn);
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.', 'field' => 'profileConfirmPassword']);
        ob_end_flush();
        exit;
    }

    $pending_hash = password_hash($new_password, PASSWORD_DEFAULT);
    $otp_code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $now = date('Y-m-d H:i:s');
    $expires_at = date('Y-m-d H:i:s', time() + 600);

    if ($GLOBALS['use_postgres']) {
        $upsert = "INSERT INTO password_change_otp (user_id, otp_code, pending_hash, created_at, expires_at) VALUES (?, ?, ?, ?, ?)
                   ON CONFLICT (user_id) DO UPDATE SET otp_code = EXCLUDED.otp_code, pending_hash = EXCLUDED.pending_hash, created_at = EXCLUDED.created_at, expires_at = EXCLUDED.expires_at";
    } else {
        $upsert = "INSERT OR REPLACE INTO password_change_otp (user_id, otp_code, pending_hash, created_at, expires_at) VALUES (?, ?, ?, ?, ?)";
    }
    $upsert_result = execute_sql($conn, $upsert, [$user_id, $otp_code, $pending_hash, $now, $expires_at]);
    if ($upsert_result === false) {
        close_connection($conn);
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not start password verification. Please try again.']);
        ob_end_flush();
        exit;
    }

    $email_sent = send_password_change_otp_email_brevo($current_user['email'], $otp_code, $current_user['username'] ?? 'there');
    if (!$email_sent && get_brevo_service() !== null) {
        execute_sql($conn, "DELETE FROM password_change_otp WHERE user_id = ?", [$user_id]);
        close_connection($conn);
        ob_clean();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'We could not send the verification code to your email. Please try again.']);
        ob_end_flush();
        exit;
    }

    close_connection($conn);
    ob_clean();
    echo json_encode(['success' => true, 'message' => 'Verification code sent.', 'email' => $current_user['email']]);
    ob_end_flush();
    exit;
} catch (Exception $e) {
    error_log("Error sending password-change OTP: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    ob_end_flush();
    exit;
}
?>
