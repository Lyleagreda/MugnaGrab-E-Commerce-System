<?php
session_start();

// Redirect to home if already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header('Location: home.php');
    exit;
}

// Check for registration errors or success message
$error = '';
$success = '';

if (isset($_SESSION['register_error'])) {
    $error = $_SESSION['register_error'];
    unset($_SESSION['register_error']);
}

if (isset($_SESSION['register_success'])) {
    $success = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
}

// Retrieve old input if available
$old = $_SESSION['old_input'] ?? [];
unset($_SESSION['old_input']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Mugna</title>
    <link rel="stylesheet" href="css/signup-new.css">
    <link rel="shortcut icon" href="./images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Logo -->
        <div class="logo">
            <img src="images/mugna-logo.png" alt="Mugna">
        </div>
        
        <!-- Main Content -->
        <div class="content">
            <!-- Left Column - Image -->
            <div class="login-image">
                <div class="image-container">
                    <img src="images/mugna-login.jpg" alt="Mugna Leather Arts">
                    <div class="image-overlay">
                        <h2>Where hands create, souls speak</h2>
                        <p>Mugna Leather Arts aims to bring creativeness and artistry out of its customers</p>
                        <div class="slider-dots">
                            <span class="dot active"></span>
                            <span class="dot"></span>
                            <span class="dot"></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Form -->
            <div class="form-column">
                <div class="form-card">
                    <h2>Create an Account</h2>
                    <p>Join Mugna Leather Arts where quality leather meets timeless style</p>
                    
                    <?php if ($error): ?>
                        <div class="error-message"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form action="register.php" method="post" id="signupForm">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo $old['first_name'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo $old['last_name'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <div class="input-with-icon">
                                <span class="input-icon">
                                    <i class="fas fa-envelope"></i>
                                </span>
                                <input style="padding-left: 50px;" type="email" id="email" name="email" placeholder="you@example.com" value="<?php echo $old['email'] ?? ''; ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Mobile Number (Philippines)</label>
                            <div class="input-with-icon">
                                <span class="input-icon">
                                    <i class="fas fa-phone"></i>
                                </span>
                                <input style="padding-left: 50px;" type="tel" id="phone" name="phone" placeholder="09XX XXX XXXX" value="<?php echo $old['phone'] ?? ''; ?>" required>
                            </div>
                            <div class="input-hint">Format: 09XX XXX XXXX or +63 9XX XXX XXXX</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-with-icon">
                                <span class="input-icon">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input style="padding-left: 50px;" type="password" id="password" name="password" required>
                            </div>
                            <div class="input-hint">At least 8 characters with letters and numbers</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password_confirm">Confirm Password</label>
                            <div class="input-with-icon">
                                <span class="input-icon">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input style="padding-left: 50px;" type="password" id="password_confirm" name="password_confirm" required>
                            </div>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <label class="checkbox-label">
                                <input type="checkbox" name="terms" id="terms" required>
                                <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></span>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn-primary" id="signupButton">Create Account</button>
                        
                        <div class="login-link">
                            Already have an account? <a href="index.php">Sign in</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="js/signup-new.js"></script>
</body>
</html>

