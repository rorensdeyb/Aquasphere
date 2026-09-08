<?php
/**
 * Save System Settings API
 * Note: Brevo settings (API key, sender email, sender name) are now configured
 * via environment variables: BREVO_API_KEY, BREVO_SENDER_EMAIL, BREVO_SENDER_NAME
 */

// Start output buffering to catch any unexpected output
ob_start();

// Set headers first
header('Content-Type: application/json');

// Suppress any warnings/notices that might break JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../database.php';
require_once '../sanitize.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = sanitize_array_recursive(json_decode(file_get_contents('php://input'), true));

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

$user_id = 1; // TODO: Get from session

// Save non-Brevo settings only (Brevo is configured via environment variables)
$settings_to_save = [
    'site_name',
    'site_description',
    'max_users',
    'session_timeout',
    'password_min_length',
    'max_login_attempts',
    'enable_two_factor'
];

$saved_settings = [];
$errors = [];

// Initialize database to ensure table exists
init_db();

foreach ($settings_to_save as $key) {
    if (isset($data[$key])) {
        $value = $data[$key];
        if (is_string($value)) {
            $value = assert_safe_string($value, $key, 255);
        }
        error_log("Saving setting: $key = $value");
        $result = update_system_setting($key, $value, $user_id);
        if ($result) {
            $saved_settings[] = $key;
            $verify = get_system_setting($key);
            if ($verify !== $value) {
                error_log("Setting $key verification failed");
                $errors[] = "Setting $key was not saved correctly";
            } else {
                error_log("Setting $key saved and verified successfully");
            }
        } else {
            error_log("Failed to save setting: $key");
            $errors[] = "Failed to save setting: $key";
        }
    }
}

// Log the save operation
error_log("Settings saved: " . implode(', ', $saved_settings));

// Clear any output buffer before sending JSON
ob_clean();

if (empty($errors)) {
    echo json_encode([
        'success' => true,
        'message' => 'Settings saved successfully',
        'saved_count' => count($saved_settings),
        'note' => 'Brevo settings are configured via environment variables (BREVO_API_KEY, BREVO_SENDER_EMAIL, BREVO_SENDER_NAME)'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Some settings may not have been saved correctly',
        'errors' => $errors,
        'saved_count' => count($saved_settings)
    ]);
}

// End output buffering
ob_end_flush();
exit;
?>

