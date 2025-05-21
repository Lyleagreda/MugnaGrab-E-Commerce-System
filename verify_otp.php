<?php
session_start();
require_once 'db.php';  // Adjust if your DB connection path is different

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['login_error'] = 'Invalid request.';
    header('Location: index.php');
    exit;
}

if (!isset($_POST['email'], $_POST['otp'], $_POST['new_password'], $_POST['confirm_password'])) {
    $_SESSION['login_error'] = 'All fields are required.';
    header('Location: index.php');
    exit;
}

$email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
$otp = trim($_POST['otp']);
$newPassword = $_POST['new_password'];
$confirmPassword = $_POST['confirm_password'];

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $_SESSION['login_error'] = 'Invalid email format.';
    header('Location: index.php');
    exit;
}

// Validate OTP format
if (!preg_match('/^\d{6}$/', $otp)) {
    $_SESSION['login_error'] = 'Invalid OTP format.';
    header('Location: index.php');
    exit;
}

// Validate password length
if (strlen($newPassword) < 8) {
    $_SESSION['login_error'] = 'Password must be at least 8 characters long.';
    header('Location: index.php');
    exit;
}

// Check if passwords match
if ($newPassword !== $confirmPassword) {
    $_SESSION['login_error'] = 'Passwords do not match.';
    header('Location: index.php');
    exit;
}

try {
    // Check if the email matches the session email
    if ($email !== $_SESSION['reset_email']) {
        $_SESSION['login_error'] = 'Email mismatch.';
        header('Location: index.php');
        exit;
    }

    // Check if the user exists
    $stmt = $conn->prepare("SELECT id, token, expires_at FROM users WHERE email = :email");
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['login_error'] = 'Email not found.';
        header('Location: index.php');
        exit;
    }

    // Check OTP validity
    if ($otp !== $user['token'] || strtotime($user['expires_at']) < time()) {
        $_SESSION['login_error'] = 'Invalid or expired OTP.';
        header('Location: index.php');
        exit;
    }

    // Update password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = :password WHERE email = :email");
    $stmt->bindParam(':password', $newPasswordHash);
    $stmt->bindParam(':email', $email);
    if ($stmt->execute()) {
        // Clear OTP after successful password update
        $stmt = $conn->prepare("UPDATE users SET token = '', expires_at = NULL WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        // Set session success message
        $_SESSION['password_reset_success'] = 'Password reset successfully!';

        // Log the user in after reset
        $_SESSION['logged_in'] = true;
        $_SESSION['user_email'] = $email;

        // Redirect based on user role (you can change this as per your requirement)
        $_SESSION['role'] = 'user';  // Assuming regular user, change to 'admin' if admin is logging in

        // Redirect to homepage (or a different page if admin)
        header('Location: home.php');
        exit;
    } else {
        $_SESSION['login_error'] = 'Error updating password.';
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    $_SESSION['login_error'] = 'Error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}
