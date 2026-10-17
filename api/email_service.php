<?php
/**
 * Email Service for AquaSphere using Brevo (formerly Sendinblue) API
 */

require_once 'database.php';

class BrevoEmailService {
    private $api_key;
    private $sender_email;
    private $sender_name;
    private $api_url = "https://api.brevo.com/v3/smtp/email";
    
    public function __construct($api_key, $sender_email, $sender_name = "AquaSphere") {
        $this->api_key = $api_key;
        $this->sender_email = $sender_email;
        $this->sender_name = $sender_name;
    }
    
    /**
     * Send email using Brevo API
     */
    public function send_email($to_email, $subject, $html_content, $text_content = null, $to_name = null) {
        if (!$this->api_key || !$this->sender_email) {
            return ['success' => false, 'message' => 'Email service not configured'];
        }
        
        $headers = [
            "accept: application/json",
            "api-key: " . $this->api_key,
            "content-type: application/json"
        ];
        
        $data = [
            "sender" => [
                "email" => $this->sender_email,
                "name" => $this->sender_name
            ],
            "to" => [
                [
                    "email" => $to_email,
                    "name" => $to_name ? $to_name : explode('@', $to_email)[0]
                ]
            ],
            "subject" => $subject,
            "htmlContent" => $html_content
        ];
        
        if ($text_content) {
            $data["textContent"] = $text_content;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['success' => false, 'message' => 'CURL Error: ' . $error];
        }
        
        if ($http_code == 201) {
            $response_data = json_decode($response, true);
            return ['success' => true, 'message' => 'Email sent successfully', 'message_id' => $response_data['messageId'] ?? null];
        } else {
            $error_data = json_decode($response, true);
            $error_message = $error_data['message'] ?? 'Unknown error';
            return ['success' => false, 'message' => 'Brevo API Error: ' . $error_message];
        }
    }
}

/**
 * Get configured Brevo email service
 * Reads from environment variables (BREVO_API_KEY, BREVO_SENDER_EMAIL, BREVO_SENDER_NAME)
 */
function get_brevo_service() {
    $api_key = getenv('BREVO_API_KEY') ?: '';
    $sender_email = getenv('BREVO_SENDER_EMAIL') ?: '';
    $sender_name = getenv('BREVO_SENDER_NAME') ?: 'AquaSphere';
    
    if (!$api_key || !$sender_email) {
        error_log("Brevo service not available - API key: " . (!empty($api_key) ? "SET" : "NOT SET") . ", sender email: " . (!empty($sender_email) ? "SET" : "NOT SET"));
        return null;
    }
    
    error_log("Brevo service initialized from environment variables");
    return new BrevoEmailService($api_key, $sender_email, $sender_name);
}

/**
 * Send OTP email using Brevo
 */
function send_otp_email_brevo($email, $otp_code, $username) {
    $brevo_service = get_brevo_service();
    
    if (!$brevo_service) {
        // Development fallback - log the OTP so it can be retrieved from logs
        error_log("Brevo email service not configured. OTP for $username ($email): $otp_code");
        // Return false to indicate email was not sent
        // But we'll still allow registration to proceed in development
        return false;
    }
    
    $subject = "AquaSphere - Email Verification Code";
    
    $html_content = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Email Verification</title>
        <style>
            body { font-family: Inter, system-ui, Segoe UI, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2383B5, #155A7A); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 20px; background-color: #f9f9f9; border-radius: 0 0 8px 8px; }
            .otp-box { background-color: white; padding: 30px; margin: 20px 0; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .otp-code { font-size: 36px; font-weight: bold; color: #2383B5; letter-spacing: 8px; margin: 20px 0; }
            .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>AquaSphere Email Verification</h1>
            </div>
            <div class='content'>
                <h2>Hello $username,</h2>
                <p>Thank you for registering with AquaSphere! To complete your registration, please use the verification code below:</p>
                
                <div class='otp-box'>
                    <h3>Your Verification Code</h3>
                    <div class='otp-code'>$otp_code</div>
                    <p>Enter this code in the verification page to complete your registration.</p>
                </div>
                
                <div class='warning'>
                    <strong>Important:</strong>
                    <ul style='margin: 10px 0; padding-left: 20px;'>
                        <li>This code will expire in 10 minutes</li>
                        <li>Do not share this code with anyone</li>
                        <li>If you didn't request this code, please ignore this email</li>
                    </ul>
                </div>
                
                <p>If you have any questions, please contact our support team.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from AquaSphere. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $text_content = "
    AquaSphere Email Verification
    
    Hello $username,
    
    Thank you for registering with AquaSphere! To complete your registration, please use the verification code below:
    
    Your Verification Code: $otp_code
    
    Enter this code in the verification page to complete your registration.
    
    Important:
    - This code will expire in 10 minutes
    - Do not share this code with anyone
    - If you didn't request this code, please ignore this email
    
    If you have any questions, please contact our support team.
    
    This is an automated message from AquaSphere. Please do not reply to this email.
    ";
    
    $result = $brevo_service->send_email($email, $subject, $html_content, $text_content, $username);
    return $result['success'];
}

/**
 * Send password reset OTP email using Brevo
 */
function send_password_reset_otp_email_brevo($email, $otp_code, $username) {
    $brevo_service = get_brevo_service();
    
    if (!$brevo_service) {
        // Development fallback - log the OTP so it can be retrieved from logs
        error_log("Brevo email service not configured. Password Reset OTP for $username ($email): $otp_code");
        // Return false to indicate email was not sent
        // But we'll still allow password reset to proceed in development
        return false;
    }
    
    $subject = "AquaSphere - Password Reset Code";
    
    $html_content = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Password Reset</title>
        <style>
            body { font-family: Inter, system-ui, Segoe UI, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2383B5, #155A7A); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 20px; background-color: #f9f9f9; border-radius: 0 0 8px 8px; }
            .otp-box { background-color: white; padding: 30px; margin: 20px 0; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .otp-code { font-size: 36px; font-weight: bold; color: #2383B5; letter-spacing: 8px; margin: 20px 0; }
            .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>AquaSphere Password Reset</h1>
            </div>
            <div class='content'>
                <h2>Hello $username,</h2>
                <p>We received a request to reset your password. Please use the verification code below to proceed:</p>
                
                <div class='otp-box'>
                    <h3>Your Reset Code</h3>
                    <div class='otp-code'>$otp_code</div>
                    <p>Enter this code in the password reset page to continue.</p>
                </div>
                
                <div class='warning'>
                    <strong>Important:</strong>
                    <ul style='margin: 10px 0; padding-left: 20px;'>
                        <li>This code will expire in 10 minutes</li>
                        <li>Do not share this code with anyone</li>
                        <li>If you didn't request this reset, please ignore this email</li>
                    </ul>
                </div>
                
                <p>If you have any questions, please contact our support team.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from AquaSphere. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $text_content = "
    AquaSphere Password Reset
    
    Hello $username,
    
    We received a request to reset your password. Please use the verification code below to proceed:
    
    Your Reset Code: $otp_code
    
    Enter this code in the password reset page to continue.
    
    Important:
    - This code will expire in 10 minutes
    - Do not share this code with anyone
    - If you didn't request this reset, please ignore this email
    
    If you have any questions, please contact our support team.
    
    This is an automated message from AquaSphere. Please do not reply to this email.
    ";
    
    $result = $brevo_service->send_email($email, $subject, $html_content, $text_content, $username);
    return $result['success'];
}

/**
 * Send email-change verification OTP email using Brevo
 */
function send_email_change_otp_email_brevo($email, $otp_code, $username) {
    $brevo_service = get_brevo_service();
    
    if (!$brevo_service) {
        // Development fallback - log the OTP so it can be retrieved from logs
        error_log("Brevo email service not configured. Email Change OTP for $username ($email): $otp_code");
        // Return false to indicate email was not sent
        // But we'll still allow the verification to proceed in development
        return false;
    }
    
    $subject = "AquaSphere - Verify Your New Email";
    
    $html_content = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Verify Your New Email</title>
        <style>
            body { font-family: Inter, system-ui, Segoe UI, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2383B5, #155A7A); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 20px; background-color: #f9f9f9; border-radius: 0 0 8px 8px; }
            .otp-box { background-color: white; padding: 30px; margin: 20px 0; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .otp-code { font-size: 36px; font-weight: bold; color: #2383B5; letter-spacing: 8px; margin: 20px 0; }
            .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Confirm Your New Email</h1>
            </div>
            <div class='content'>
                <h2>Hello $username,</h2>
                <p>You requested to change the email on your AquaSphere account to this address. Please use the verification code below to confirm:</p>
                
                <div class='otp-box'>
                    <h3>Your Verification Code</h3>
                    <div class='otp-code'>$otp_code</div>
                    <p>Enter this code in the verification popup to save your new email.</p>
                </div>
                
                <div class='warning'>
                    <strong>Important:</strong>
                    <ul style='margin: 10px 0; padding-left: 20px;'>
                        <li>This code will expire in 10 minutes</li>
                        <li>Do not share this code with anyone</li>
                        <li>If you didn't request this change, please ignore this email</li>
                    </ul>
                </div>
                
                <p>If you have any questions, please contact our support team.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from AquaSphere. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $text_content = "
    AquaSphere Email Verification
    
    Hello $username,
    
    You requested to change the email on your AquaSphere account to this address. Please use the verification code below to confirm:
    
    Your Verification Code: $otp_code
    
    Enter this code in the verification popup to save your new email.
    
    Important:
    - This code will expire in 10 minutes
    - Do not share this code with anyone
    - If you didn't request this change, please ignore this email
    
    If you have any questions, please contact our support team.
    
    This is an automated message from AquaSphere. Please do not reply to this email.
    ";
    
    $result = $brevo_service->send_email($email, $subject, $html_content, $text_content, $username);
    return $result['success'];
}

/**
 * Send password-change verification OTP email using Brevo (BondNest-style)
 */
function send_password_change_otp_email_brevo($email, $otp_code, $username) {
    $brevo_service = get_brevo_service();

    if (!$brevo_service) {
        error_log("Brevo email service not configured. Password Change OTP for $username ($email): $otp_code");
        return false;
    }

    $subject = "AquaSphere - Verify your password change";

    $html_content = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Verify your password change</title>
        <style>
            body { font-family: Inter, system-ui, Segoe UI, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #2383B5, #155A7A); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { padding: 20px; background-color: #f9f9f9; border-radius: 0 0 8px 8px; }
            .otp-box { background-color: white; padding: 30px; margin: 20px 0; border-radius: 8px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .otp-code { font-size: 36px; font-weight: bold; color: #2383B5; letter-spacing: 8px; margin: 20px 0; }
            .warning { background-color: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Verify Your Password Change</h1>
            </div>
            <div class='content'>
                <h2>Hello $username,</h2>
                <p>You requested to change your AquaSphere password. Please use the verification code below to confirm:</p>

                <div class='otp-box'>
                    <h3>Your Verification Code</h3>
                    <div class='otp-code'>$otp_code</div>
                    <p>Enter this code in the verification popup to confirm your password change.</p>
                </div>

                <div class='warning'>
                    <strong>Important:</strong>
                    <ul style='margin: 10px 0; padding-left: 20px;'>
                        <li>This code will expire in 10 minutes</li>
                        <li>Do not share this code with anyone</li>
                        <li>If you didn't request this change, please ignore this email</li>
                    </ul>
                </div>

                <p>If you have any questions, please contact our support team.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from AquaSphere. Please do not reply to this email.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    $text_content = "
    AquaSphere Password Change Verification

    Hello $username,

    You requested to change your AquaSphere password. Please use the verification code below to confirm:

    Your Verification Code: $otp_code

    Enter this code in the verification popup to confirm your password change.

    Important:
    - This code will expire in 10 minutes
    - Do not share this code with anyone
    - If you didn't request this change, please ignore this email

    If you have any questions, please contact our support team.

    This is an automated message from AquaSphere. Please do not reply to this email.
    ";

    $result = $brevo_service->send_email($email, $subject, $html_content, $text_content, $username);
    return $result['success'];
}

/**
 * Send account suspension email
 */
function send_suspension_email_brevo($email, $username, $reason) {
    $brevo_service = get_brevo_service();
    if (!$brevo_service) {
        error_log("Suspension notice for $username ($email): $reason");
        return false;
    }

    $username_safe = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $reason_html = nl2br(htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'));
    $contact_link = 'mailto:support@aquasphere.com';

    $subject = "AquaSphere - Account Suspended";
    $html_content = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Suspended</title>
</head>
<body style="margin:0;padding:0;background:#eef1f4;font-family:Inter,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef1f4;">
<tr>
<td align="center" style="padding:32px 16px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #e6e9ee;border-radius:16px;overflow:hidden;">
<tr>
<td style="background:#2c7ea6;padding:26px 28px;">
<div style="color:rgba(255,255,255,0.78);font-size:12px;font-weight:700;letter-spacing:3px;text-transform:uppercase;">AquaSphere Admin</div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:16px;">
<tr>
<td style="vertical-align:middle;padding-right:16px;">
<div style="width:48px;height:48px;border-radius:50%;background:#f5c518;color:#1f2937;font-size:26px;font-weight:800;text-align:center;line-height:48px;">!</div>
</td>
<td style="vertical-align:middle;">
<div style="color:#ffffff;font-size:25px;font-weight:800;line-height:1.15;">Account Suspended</div>
<div style="color:rgba(255,255,255,0.9);font-size:14px;margin-top:6px;">This is a temporary hold on your account.</div>
</td>
</tr>
</table>
</td>
</tr>
<tr>
<td style="padding:28px;">
<p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#1f2937;">Hello {$username_safe},</p>
<p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#3a4653;">Your AquaSphere account has been <strong>temporarily suspended</strong>.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2f6;border-radius:10px;">
<tr>
<td width="50%" style="padding:16px 18px;border-right:1px solid #dfe5ec;vertical-align:top;">
<div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;">Status</div>
<div style="font-size:15px;font-weight:700;color:#1f2937;margin-top:6px;"><span style="color:#f59e0b;">&#9679;</span> Temporarily suspended</div>
</td>
<td width="50%" style="padding:16px 18px;vertical-align:top;">
<div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;">Decided by</div>
<div style="font-size:15px;font-weight:700;color:#1f2937;margin-top:6px;">AquaSphere Admin</div>
</td>
</tr>
</table>
<div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;margin:22px 0 8px;">Reason provided by admin</div>
<div style="background:#fdf6e7;border-left:4px solid #f0b429;border-radius:8px;padding:16px 18px;font-size:15px;line-height:1.6;color:#4a4536;">{$reason_html}</div>
<p style="margin:22px 0 18px;font-size:15px;line-height:1.6;color:#3a4653;">If you believe this is a mistake or need more information, please contact the AquaSphere team.</p>
<a href="{$contact_link}" style="display:inline-block;background:#2c7ea6;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 26px;border-radius:8px;">Contact AquaSphere</a>
<div style="border-top:1px solid #e6e9ee;margin:28px 0 16px;"></div>
<p style="margin:0;font-size:13px;line-height:1.5;color:#97a3b0;">This is an automated message from <strong style="color:#64748b;">AquaSphere Admin</strong> about a change to your account.</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
HTML;

    $text_content = "Hello $username,\n\nYour AquaSphere account has been temporarily suspended.\n\nReason provided by admin:\n$reason\n\nIf you believe this is a mistake or need more information, please contact the AquaSphere team (support@aquasphere.com).\n\nThis is an automated message from AquaSphere Admin about a change to your account.";

    $result = $brevo_service->send_email($email, $subject, $html_content, $text_content, $username);
    return $result['success'];
}

/**
 * Send account unsuspension email
 */
function send_unsuspension_email_brevo($email, $username) {
    $brevo_service = get_brevo_service();
    if (!$brevo_service) {
        error_log("Unsuspension notice for $username ($email)");
        return false;
    }

    $username_safe = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $contact_link = 'mailto:support@aquasphere.com';
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'aquasphere.up.railway.app';
    $signin_link = $scheme . '://' . $host . '/login.html';

    $subject = "AquaSphere - Suspension Lifted";
    $html_content = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Suspension Lifted</title>
</head>
<body style="margin:0;padding:0;background:#eef1f4;font-family:Inter,-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef1f4;">
<tr>
<td align="center" style="padding:32px 16px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #e6e9ee;border-radius:16px;overflow:hidden;">
<tr>
<td style="background:#22a44e;padding:26px 28px;">
<div style="color:rgba(255,255,255,0.82);font-size:12px;font-weight:700;letter-spacing:3px;text-transform:uppercase;">AquaSphere Admin</div>
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:16px;">
<tr>
<td style="vertical-align:middle;padding-right:16px;">
<div style="width:48px;height:48px;border-radius:50%;background:#ffffff;color:#22a44e;font-size:26px;font-weight:800;text-align:center;line-height:48px;">&#10003;</div>
</td>
<td style="vertical-align:middle;">
<div style="color:#ffffff;font-size:25px;font-weight:800;line-height:1.15;">Suspension Lifted</div>
<div style="color:rgba(255,255,255,0.92);font-size:14px;margin-top:6px;">Your account is active again.</div>
</td>
</tr>
</table>
</td>
</tr>
<tr>
<td style="padding:28px;">
<p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#1f2937;">Hello {$username_safe},</p>
<p style="margin:0 0 22px;font-size:15px;line-height:1.6;color:#3a4653;">Your AquaSphere account suspension has been <strong style="text-decoration:underline;color:#1f2937;">lifted</strong>. You can now sign in again.</p>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2f6;border-radius:10px;">
<tr>
<td width="50%" style="padding:16px 18px;border-right:1px solid #dfe5ec;vertical-align:top;">
<div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;">Status</div>
<div style="font-size:15px;font-weight:700;color:#1f2937;margin-top:6px;"><span style="color:#22c55e;">&#9679;</span> Active</div>
</td>
<td width="50%" style="padding:16px 18px;vertical-align:top;">
<div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#94a3b8;">Sign in</div>
<div style="font-size:15px;font-weight:700;color:#1f2937;margin-top:6px;">Available again</div>
</td>
</tr>
</table>
<div style="margin:24px 0 20px;">
<a href="{$signin_link}" style="display:inline-block;background:#22a44e;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 26px;border-radius:8px;">Sign in to AquaSphere</a>
</div>
<p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#3a4653;">If you have any concerns, please <a href="{$contact_link}" style="color:#2c7ea6;font-weight:700;text-decoration:underline;">contact support</a>.</p>
<p style="margin:0 0 4px;font-size:15px;line-height:1.6;color:#3a4653;">Thank you,<br><strong style="color:#1f2937;">AquaSphere Team</strong></p>
<div style="border-top:1px solid #e6e9ee;margin:28px 0 16px;"></div>
<p style="margin:0;font-size:13px;line-height:1.5;color:#97a3b0;">This is an automated message from <strong style="color:#64748b;">AquaSphere Admin</strong> about a change to your account.</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
HTML;

    $text_content = "Hello $username,\n\nYour AquaSphere account suspension has been lifted. You can now sign in again.\n\nIf you have any concerns, please contact support (support@aquasphere.com).\n\nThank you,\nAquaSphere Team\n\nThis is an automated message from AquaSphere Admin about a change to your account.";

    $result = $brevo_service->send_email($email, $subject, $html_content, $text_content, $username);
    return $result['success'];
}
?>

