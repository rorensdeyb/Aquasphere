<?php
/**
 * Login Handler for AquaSphere
 */

// Start output buffering to catch any unexpected output
ob_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set headers first
header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once 'sanitize.php';
require_once 'database.php';

// Get form data
$username = assert_safe_string($_POST['username'] ?? '', 'username', 64);
$password = $_POST['password'] ?? '';
$remember_me = isset($_POST['remember_me']) ? 1 : 0;

try {
    // Validation
    $errors = [];
    
    // Username validation (accepts email or username)
    if (empty($username)) {
        $errors['username'] = 'Username or email is required.';
    } elseif (strlen($username) < 4 || strlen($username) > 128) {
        $errors['username'] = 'Username must be between 4 and 128 characters long.';
    }
    
    // Password validation
    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    }
    
    // If there are errors, return them
    if (!empty($errors)) {
        ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'errors' => $errors]);
        ob_end_flush();
        exit;
    }
    
    // Verify user credentials (support both username and email login)
    $conn = get_db_connection();
    $query = "SELECT * FROM users WHERE username = ? OR email = ?";
    $result = execute_sql($conn, $query, [$username, $username]);
    
    if ($GLOBALS['use_postgres']) {
        $user = pg_fetch_assoc($result);
    } else {
        $user = $result->fetchArray(SQLITE3_ASSOC);
    }
    
    // Log for debugging
    if ($user) {
        error_log("Login attempt - Username: " . $username . ", User ID: " . $user['id']);
    } else {
        error_log("Login attempt - User not found for username: " . $username);
    }
    
    if ($user && password_verify($password, $user['password_hash'])) {
        // Block suspended accounts
        if (isset($user['suspended']) && intval($user['suspended']) === 1) {
            close_connection($conn);
            ob_clean();
            http_response_code(403);
            $reason = $user['suspension_reason'] ?? 'Your account is suspended.';
            echo json_encode(['success' => false, 'message' => "Account suspended: $reason"]);
            ob_end_flush();
            exit;
        }

        // Auto-promote admin email users
        $is_admin = (int)($user['is_admin'] ?? 0);
        if ($is_admin === 0 && isConfiguredAdminEmail($user['email'] ?? '')) {
            $is_admin = 1;
            execute_sql($conn, "UPDATE users SET is_admin = 1 WHERE id = ?", [$user['id']]);
        }

        // Update last login time
        $updateQuery = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?";
        execute_sql($conn, $updateQuery, [$user['id']]);
        
        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_admin'] = $is_admin;
        
        // Set remember me cookie if checked
        if ($remember_me) {
            $cookie_value = base64_encode($user['id'] . ':' . hash('sha256', $user['password_hash']));
            setcookie('aquasphere_remember', $cookie_value, time() + (86400 * 30), '/'); // 30 days
        }
        
        close_connection($conn);
        
        // Check if user is admin and redirect accordingly
        $redirect_url = $is_admin ? 'admin/dashboard.html' : 'dashboard.html';
        
        // Clear any output buffer before sending JSON
        ob_clean();
        echo json_encode([
            'success' => true, 
            'message' => 'Login successful!',
            'redirect' => $redirect_url
        ]);
        ob_end_flush();
        exit;
    } else {
        close_connection($conn);
        ob_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'errors' => ['password' => 'Invalid username or password.']]);
        ob_end_flush();
        exit;
    }
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    $error_message = $e->getMessage();
    if (empty($error_message)) {
        $error_message = 'Unknown error occurred. Check PHP error logs.';
    }
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $error_message,
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    ob_end_flush();
    exit;
}
?>

