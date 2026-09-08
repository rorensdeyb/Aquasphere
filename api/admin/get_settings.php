<?php
/**
 * Get System Settings API
 */

header('Content-Type: application/json');
require_once '../database.php';
require_once '../sanitize.php';

// Check if user is admin (you should implement proper session/auth check)
// For now, we'll just return the settings

$settings = get_all_system_settings();

// Brevo settings come from environment variables, not database
$brevo_api_key = getenv('BREVO_API_KEY') ?: '';
$brevo_sender_email = getenv('BREVO_SENDER_EMAIL') ?: '';
$brevo_sender_name = getenv('BREVO_SENDER_NAME') ?: 'AquaSphere';

// Set defaults for missing settings
$defaults = [
    'site_name' => 'AquaSphere',
    'site_description' => 'Clean water delivery service',
    'max_users' => '1000',
    'session_timeout' => '30',
    'password_min_length' => '8',
    'max_login_attempts' => '5',
    'enable_two_factor' => '0'
];

foreach ($defaults as $key => $default_value) {
    if (!isset($settings[$key])) {
        $settings[$key] = $default_value;
    }
}

// Add Brevo settings from environment variables
$settings['brevo_api_key'] = !empty($brevo_api_key) ? '***SAVED***' : '';
$settings['brevo_sender_email'] = $brevo_sender_email;
$settings['brevo_sender_name'] = $brevo_sender_name;
$settings['brevo_config_source'] = 'environment'; // Indicate settings come from env vars

echo json_encode([
    'success' => true,
    'settings' => $settings
]);
?>

