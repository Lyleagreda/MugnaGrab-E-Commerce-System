<?php
session_start();
include('db.php');

// Check if PHPMailer files exist and include them
$phpmailer_path = 'PHPMailer/src/';
if (!file_exists($phpmailer_path . 'Exception.php') || 
    !file_exists($phpmailer_path . 'PHPMailer.php') || 
    !file_exists($phpmailer_path . 'SMTP.php')) {
    echo json_encode(['success' => false, 'message' => 'PHPMailer files not found. Please install PHPMailer correctly.']);
    exit;
}

require $phpmailer_path . 'Exception.php';
require $phpmailer_path . 'PHPMailer.php';
require $phpmailer_path . 'SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

// Check if the request is valid
if (!isset($_POST['email']) || empty($_POST['email'])) {
    echo json_encode(['success' => false, 'message' => 'Email is required']);
    exit;
}

$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

try {
    // Check if email exists in the database using PDO
    $stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'Email not found in our records']);
        exit;
    }
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userId = $user['id'];
    $firstName = $user['first_name'] ?: 'User';
    
    // Generate OTP
    $otp = sprintf("%06d", mt_rand(100000, 999999));
    $otpExpiry = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    
    // Store OTP and expiry in the users table
    $stmt = $conn->prepare("UPDATE users SET token = :token, expires_at = :expires_at WHERE id = :user_id");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':token', $otp, PDO::PARAM_STR);
    $stmt->bindParam(':expires_at', $otpExpiry, PDO::PARAM_STR);

    if (!$stmt->execute()) {
        throw new Exception("Failed to store OTP: " . implode(", ", $stmt->errorInfo()));
    }
    
    // Send OTP email using PHPMailer
    $mail = new PHPMailer(true);
    
    try {
         // Server settings
         $mail->SMTPDebug = 0;                      // Enable verbose debug output (set to 2 for debugging)
         $mail->isSMTP();                           // Send using SMTP
         $mail->Host       = 'smtp.gmail.com';      // SMTP server
         $mail->SMTPAuth   = true;                  // Enable SMTP authentication
         $mail->Username   = 'dharzdesu@gmail.com'; // SMTP username
         $mail->Password   = 'pwwv gwns hnyo bztl';  // SMTP password
         $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Enable TLS encryption
         $mail->Port       = 587;                  // TCP port to connect to

        // Recipients
        $mail->setFrom('dharzdesu@gmail.com', 'Mugna Leather Arts');
        $mail->addAddress($email);                 // Add a recipient

        // Content
        $mail->isHTML(true);                       // Set email format to HTML
        $mail->Subject = 'Password Reset OTP';
        
        // Email body
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #1e3a8a; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9f9f9; }
                .otp { font-size: 24px; font-weight: bold; text-align: center; margin: 20px 0; letter-spacing: 5px; color: #1e3a8a; }
                .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>Mugna Password Reset</h2>
                </div>
                <div class='content'>
                    <p>Hello $firstName,</p>
                    <p>We received a request to reset your password. Please use the following One-Time Password (OTP) to complete your password reset:</p>
                    <div class='otp'>$otp</div>
                    <p>This OTP will expire in 5 minutes.</p>
                    <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated email, please do not reply. If you need assistance, contact our support team.</p>
                    <p>&copy; " . date('Y') . " Mugna Leather Arts. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        $mail->send();
        
        // Store email in session for verification
        $_SESSION['reset_email'] = $email;
        
        echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error sending email: ' . $mail->ErrorInfo]);
        exit;
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
