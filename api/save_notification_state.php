<?php
/**
 * Save Notification State
 * Persists per-user notification seen/cleared timestamps (ms epoch) so the
 * badge state survives logout/login and redeploys. Values only move forward.
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
$seen_at = isset($input['seen_at']) ? (int)$input['seen_at'] : null;
$cleared_at = isset($input['cleared_at']) ? (int)$input['cleared_at'] : null;

try {
    require_once 'database.php';
    
    init_db();
    $conn = get_db_connection();
    $user_id = intval($_SESSION['user_id']);
    
    $fields = [];
    $params = [];
    if ($seen_at !== null && $seen_at > 0) {
        // Only move forward - never regress to an older timestamp
        $fields[] = "notif_seen_at = CASE WHEN CAST(COALESCE(NULLIF(notif_seen_at, ''), '0') AS BIGINT) < ? THEN ? ELSE notif_seen_at END";
        $params[] = (string)$seen_at;
        $params[] = (string)$seen_at;
    }
    if ($cleared_at !== null && $cleared_at > 0) {
        $fields[] = "notif_cleared_at = CASE WHEN CAST(COALESCE(NULLIF(notif_cleared_at, ''), '0') AS BIGINT) < ? THEN ? ELSE notif_cleared_at END";
        $params[] = (string)$cleared_at;
        $params[] = (string)$cleared_at;
    }
    
    if (!empty($fields)) {
        // execute_sql() translates ? placeholders per driver automatically
        $query = "UPDATE users SET " . implode(", ", $fields) . " WHERE id = ?";
        $params[] = $user_id;
        $result = execute_sql($conn, $query, $params);
        if ($result === false) {
            error_log("Failed to save notification state");
        }
    }
    
    close_connection($conn);
    ob_clean();
    echo json_encode(['success' => true]);
    ob_end_flush();
    exit;
} catch (Exception $e) {
    error_log("Error saving notification state: " . $e->getMessage());
    ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    ob_end_flush();
    exit;
}
?>
