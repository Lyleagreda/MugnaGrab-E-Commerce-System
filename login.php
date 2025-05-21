<?php
session_start();
require 'db.php'; // Connect to database

// Load .env variables using vlucas/phpdotenv
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Only handle POST request
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     // 1. reCAPTCHA Validation
//     $recaptchaSecret = $_ENV['RECAPTCHA_SECRET_KEY'];
//     $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

//     if (empty($recaptchaResponse)) {
//         $_SESSION['error'] = "Please complete the captcha.";
//         header("Location: index.php");
//         exit;
//     }

//     $data = [
//         'secret' => $recaptchaSecret,
//         'response' => $recaptchaResponse
//     ];

//     $options = [
//         'http' => [
//             'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
//             'method'  => 'POST',
//             'content' => http_build_query($data),
//         ]
//     ];

//     $context = stream_context_create($options);
//     $verify = file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);

//     if ($verify === false) {
//         $_SESSION['error'] = "Failed to contact reCAPTCHA server.";
//         header("Location: index.php");
//         exit;
//     }

//     $captchaSuccess = json_decode($verify, true);

//     if (!isset($captchaSuccess['success']) || !$captchaSuccess['success']) {
//         $_SESSION['error'] = "Captcha verification failed. Error: " . implode(', ', $captchaSuccess['error-codes'] ?? ['Unknown error']);
//         header("Location: index.php");
//         exit;
//     }

// Check if password reset was successful
if (isset($_SESSION['password_reset_success'])) {
    echo "<script>alert('" . $_SESSION['password_reset_success'] . "');</script>";
    unset($_SESSION['password_reset_success']);  // Clear the success message after displaying it
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = 'Email and password are required';
        header('Location: index.php');
        exit;
    }

    // Check if user is admin
    $stmt_admin = $conn->prepare("SELECT id, password FROM admin WHERE email = :email");
    $stmt_admin->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt_admin->execute();

    // If user is admin
    if ($stmt_admin->rowCount() > 0) {
        $admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);
        $id = $admin['id'];
        $stored_password = $admin['password'];

        // Verify admin password
        if (password_verify($password, $stored_password)) {
            $_SESSION['logged_in'] = true;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_id'] = $id;  // Add user ID to session
            $_SESSION['role'] = 'admin'; // Assign the role as admin
            header('Location: admin/index.php'); // Redirect to admin dashboard
            exit;
        } else {
            $_SESSION['login_error'] = 'Invalid email or password';
        }
    } else {
        // Check if user exists in the regular users table
        $stmt_user = $conn->prepare("SELECT id, password FROM users WHERE email = :email");
        $stmt_user->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt_user->execute();

        if ($stmt_user->rowCount() > 0) {
            $user = $stmt_user->fetch(PDO::FETCH_ASSOC);
            $id = $user['id'];
            $stored_password = $user['password'];

            // Verify regular user password
            if (password_verify($password, $stored_password)) {
                // Update last login time for user
                $update_login = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
                $update_login->bindParam(':id', $id);
                $update_login->execute();

                $_SESSION['logged_in'] = true;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_id'] = $id;  // Add user ID to session
                header('Location: home.php'); // Redirect to user homepage
                exit;
            } else {
                $_SESSION['login_error'] = 'Invalid email or password';
            }
        } else {
            $_SESSION['login_error'] = 'Invalid email or password';
        }
    }

    header('Location: index.php');
    exit;
}

header('Location: index.php');
exit;
// }