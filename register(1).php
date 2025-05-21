<?php
session_start();
require 'db.php';
include 'includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $terms = isset($_POST['terms']);

    $_SESSION['old_input'] = [
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => $phone
    ];

    $errors = [];

    // Validate input
    if (empty($firstName)) $errors[] = 'First name is required';
    if (empty($lastName)) $errors[] = 'Last name is required';

    if (empty($email)) {
        $errors[] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }

    if (empty($phone)) {
        $errors[] = 'Mobile number is required';
    } else {
        $phone = formatPhilippinePhone($phone);
        if ($phone === false) {
            $errors[] = 'Please enter a valid Philippine mobile number';
        }
        $_SESSION['old_input']['phone'] = $phone;
    }

    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain both letters and numbers';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = 'Passwords do not match';
    }

    if (!$terms) {
        $errors[] = 'You must agree to the Terms of Service and Privacy Policy';
    }

    // Check for existing email
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $errors[] = 'Email is already registered';
    }

    if (!empty($errors)) {
        $_SESSION['register_error'] = implode('<br>', $errors);
        header('Location: signup.php');
        exit;
    }

    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user into DB
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, phone) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$firstName, $lastName, $email, $hashedPassword, $phone])) {
        unset($_SESSION['old_input']);
        $_SESSION['register_success'] = 'Account created successfully!';
        header('Location: index.php');
        exit;
    } else {
        $_SESSION['register_error'] = 'Something went wrong. Please try again.';
        header('Location: signup.php');
        exit;
    }
}

header('Location: signup.php');
exit;
?>
