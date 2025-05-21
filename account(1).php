<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

include '../config.php'; // Make sure this file contains your DB connection ($conn)

$message = '';
$messageType = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST["email"];
    $currentPassword = $_POST["current_password"];
    $newPassword = $_POST["new_password"];
    $confirmPassword = $_POST["confirm_password"];

    if ($newPassword !== $confirmPassword) {
        $message = "New password and confirm password do not match!";
        $messageType = "error";
    } else {
        // Fetch admin from the database
        $query = "SELECT * FROM admin WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            // Verify current password
            if (password_verify($currentPassword, $admin['password'])) {
                // Hash new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                // Update password in the database
                $update = "UPDATE admin SET password = ? WHERE email = ?";
                $stmt = $conn->prepare($update);
                $stmt->bind_param("ss", $hashedPassword, $email);
                $stmt->execute();

                $message = "Password changed successfully!";
                $messageType = "success";
            } else {
                $message = "Current password is incorrect!";
                $messageType = "error";
            }
        } else {
            $message = "Admin email not found!";
            $messageType = "error";
        }
    }
}

// Page title
$pageTitle = "Account Settings";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Mugna Admin</title>
    <link rel="stylesheet" href="css/admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modern UI Styles */
        :root {
            --primary-color: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --primary-bg: rgba(37, 99, 235, 0.05);
            --success-color: #10b981;
            --success-bg: rgba(16, 185, 129, 0.05);
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --danger-bg: rgba(239, 68, 68, 0.05);
            --light-bg: #f8fafc;
            --card-bg: #ffffff;
            --border-color: #e2e8f0;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --text-muted: #94a3b8;
            --border-radius: 0.75rem;
            --input-radius: 0.5rem;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --transition: all 0.3s ease;
            --transition-fast: all 0.15s ease;
        }

        body {
            background-color: var(--light-bg);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .content-wrapper {
            padding: 2rem;
        }

        .content-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 2rem;
        }

        .content-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 0;
            letter-spacing: -0.025em;
        }

        /* Card Styles */
        .card {
            background-color: var(--card-bg);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-md);
            margin-bottom: 2rem;
            overflow: hidden;
            max-width: 550px;
            margin-left: auto;
            margin-right: auto;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            letter-spacing: -0.025em;
        }

        .card-header-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .card-body {
            padding: 2rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.75rem;
            position: relative;
        }

        .form-group:last-of-type {
            margin-bottom: 0;
        }

        .form-floating {
            position: relative;
        }

        .form-floating input {
            width: 100%;
            height: 60px;
            padding: 1.25rem 1rem 0.5rem;
            border: 2px solid var(--border-color);
            border-radius: var(--input-radius);
            font-size: 1rem;
            transition: var(--transition-fast);
            background-color: var(--light-bg);
            color: var(--text-primary);
        }

        .form-floating input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
            background-color: white;
        }

        .form-floating label {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            padding: 1rem;
            pointer-events: none;
            border: 1px solid transparent;
            transform-origin: 0 0;
            transition: var(--transition-fast);
            color: var(--text-secondary);
            font-weight: 500;
        }

        .form-floating input:focus ~ label,
        .form-floating input:not(:placeholder-shown) ~ label {
            transform: scale(0.85) translateY(-0.5rem) translateX(0.15rem);
            color: var(--primary-color);
        }

        .form-floating input:focus ~ label {
            color: var(--primary-color);
        }

        .form-floating input::placeholder {
            color: transparent;
        }

        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            cursor: pointer;
            background: none;
            border: none;
            padding: 0.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition-fast);
        }

        .password-toggle:hover {
            background-color: rgba(0, 0, 0, 0.05);
            color: var(--primary-color);
        }

        /* Button Styles */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 1.5rem;
            border-radius: var(--input-radius);
            font-weight: 600;
            font-size: 1rem;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            gap: 0.5rem;
            width: 100%;
            letter-spacing: -0.025em;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            box-shadow: 0 4px 6px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 8px rgba(37, 99, 235, 0.25);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        /* Alert Styles */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            max-width: 550px;
            margin-left: auto;
            margin-right: auto;
            animation: slideDown 0.3s ease-out forwards;
            box-shadow: var(--shadow);
            border-left: 4px solid;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background-color: var(--success-bg);
            color: var(--success-color);
            border-left-color: var(--success-color);
        }

        .alert-error {
            background-color: var(--danger-bg);
            color: var(--danger-color);
            border-left-color: var(--danger-color);
        }

        .alert-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .alert-success .alert-icon {
            background-color: var(--success-color);
            color: white;
        }

        .alert-error .alert-icon {
            background-color: var(--danger-color);
            color: white;
        }

        .alert-content {
            flex: 1;
            font-weight: 500;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .content-wrapper {
                padding: 1.5rem;
            }
            
            .content-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .card, .alert {
                max-width: 100%;
            }
            
            .card-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <?php include 'includes/header.php'; ?>

            <!-- Account Content -->
            <div class="content-wrapper">
                <div class="content-header">
                    <h1>Account Settings</h1>
                </div>

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <div class="alert-icon">
                        <i class="fas fa-<?php echo $messageType === 'success' ? 'check' : 'exclamation'; ?>"></i>
                    </div>
                    <div class="alert-content">
                        <?php echo $message; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h2>Change Password</h2>
                        <div class="card-header-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="password-form" method="POST" action="">
                            <div class="form-group">
                                <div class="form-floating">
                                    <input type="email" id="email" name="email" placeholder="Email Address" required>
                                    <label for="email">Email Address</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="form-floating">
                                    <input type="password" id="current_password" name="current_password" placeholder="Current Password" required>
                                    <label for="current_password">Current Password</label>
                                    <button type="button" class="password-toggle" data-target="current_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="form-floating">
                                    <input type="password" id="new_password" name="new_password" placeholder="New Password" required>
                                    <label for="new_password">New Password</label>
                                    <button type="button" class="password-toggle" data-target="new_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <div class="form-floating">
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm New Password" required>
                                    <label for="confirm_password">Confirm New Password</label>
                                    <button type="button" class="password-toggle" data-target="confirm_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-group" style="margin-top: 2rem;">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password confirmation validation
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const passwordForm = document.getElementById('password-form');
            
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPassword = newPasswordInput.value;
                    const confirmPassword = confirmPasswordInput.value;
                    
                    if (newPassword !== confirmPassword) {
                        e.preventDefault();
                        alert('New password and confirm password do not match!');
                    }
                });
            }
            
            // Password visibility toggle
            const toggleButtons = document.querySelectorAll('.password-toggle');
            toggleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const passwordInput = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    
                    if (passwordInput.type === 'password') {
                        passwordInput.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        passwordInput.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });
        });
    </script>
</body>
</html>
