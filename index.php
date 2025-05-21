<?php
session_start();

// // Redirect to home if already logged in
// if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
//     header('Location: home.php');
//     exit;
// }

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$siteKey = $_ENV['RECAPTCHA_SITE_KEY'];

// Check for login errors
$error = '';
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

// Check for registration success message
$success = '';
if (isset($_SESSION['register_success'])) {
    $success = $_SESSION['register_success'];
    unset($_SESSION['register_success']);
}

// Check for password reset messages
$resetMessage = '';
if (isset($_SESSION['reset_message'])) {
    $resetMessage = $_SESSION['reset_message'];
    unset($_SESSION['reset_message']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mugna</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="shortcut icon" href="./images/mugna-icon.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 500px;
            position: relative;
        }

        .close-modal {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #64748b;
        }

        .close-modal:hover {
            color: #1e3a8a;
        }

        .modal-header {
            margin-bottom: 20px;
            text-align: center;
        }

        .modal-header h2 {
            color: #1e3a8a;
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .modal-header p {
            color: #64748b;
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .step {
            display: none;
        }

        .step.active {
            display: block;
        }

        #otpTimer {
            color: #64748b;
            font-size: 0.875rem;
            margin-top: 10px;
            text-align: center;
        }

        .resend-otp {
            color: #2563eb;
            text-decoration: underline;
            cursor: pointer;
        }

        .resend-otp.disabled {
            color: #94a3b8;
            cursor: not-allowed;
            text-decoration: none;
        }

        .center-recaptcha {
            display: flex;
            justify-content: center;
        }

        .google-signin-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 18px;
        }

        .google-btn {
            display: flex;
            align-items: center;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(60, 64, 67, 0.08);
            padding: 10px 24px;
            font-size: 1rem;
            color: #3c4043;
            font-weight: 500;
            cursor: pointer;
            transition: box-shadow 0.2s, border-color 0.2s;
            outline: none;
            gap: 12px;
        }

        .google-btn:hover,
        .google-btn:focus {
            border-color: #4285f4;
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.15);
        }

        .google-icon {
            width: 22px;
            height: 22px;
            display: block;
        }

        .google-btn-text {
            display: block;
            font-family: inherit;
            font-size: 1rem;
            letter-spacing: 0.01em;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <header>
            <div class="logo-container">
                <img src="images/mugna-logo.png" alt="Mugna" class="mugna-logo">
            </div>
        </header>

        <main class="login-main">
            <div class="login-grid">
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

                <div class="login-form-container">
                    <div class="login-card">
                        <div class="card-header">
                            <h2>Welcome back</h2>
                            <p>Sign in to your Mugna Leather Arts account</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="error-message"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="success-message"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <?php if ($resetMessage): ?>
                            <div class="success-message"><?php echo $resetMessage; ?></div>
                        <?php endif; ?>

                        <div class="card-content">
                            <form action="login.php" method="post" id="loginForm">
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-envelope"></i>
                                        <input type="email" id="email" name="email" placeholder="you@example.com"
                                            required>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <div class="label-with-link">
                                        <label for="password">Password</label>
                                        <a href="#" class="forgot-password" id="forgotPasswordLink">Forgot password?</a>
                                    </div>
                                    <div class="input-icon-wrapper">
                                        <i class="fas fa-lock"></i>
                                        <input type="password" id="password" name="password" required>
                                    </div>
                                </div>

                                <div class="form-group recaptcha-group center-recaptcha">
                                    <div class="g-recaptcha" data-sitekey="<?= htmlspecialchars($siteKey) ?>"></div>
                                </div>

                                <button type="submit" class="btn btn-primary btn-block" id="loginButton">
                                    <span class="btn-content">
                                        <span>Sign in</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </span>
                                    <span class="btn-loading">
                                        <span class="spinner"></span>
                                        <span>Signing in...</span>
                                    </span>
                                </button>

                                <div class="form-group google-signin-wrapper">
                                    <button type="button" class="google-btn"
                                        onclick="window.location.href='googleAuth/google_login.php'">
                                        <img src="https://www.gstatic.com/firebasejs/ui/2.0.0/images/auth/google.svg"
                                            alt="Google icon" class="google-icon">
                                        <span class="google-btn-text">Sign in with Google</span>
                                    </button>
                                </div>
                            </form>
                            <br>

                            <div class="signup-link">
                                Don't have an account? <a href="signup.php">Sign up</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>

            <div class="modal-header">
                <h2>Reset Your Password</h2>
                <p>We'll send you an OTP to reset your password</p>
            </div>

            <!-- Step 1: Enter Email -->
            <div class="step active" id="step1">
                <div class="modal-body">
                    <form id="forgotPasswordForm" action="reset_password.php" method="post">
                        <div class="form-group">
                            <label for="reset-email">Email Address</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="reset-email" name="email" placeholder="Enter your email address"
                                    required>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="cancelResetBtn">Cancel</button>
                    <button type="button" class="btn btn-primary" id="sendOtpBtn">Send OTP</button>
                </div>
            </div>

            <!-- Step 2: Enter OTP and New Password -->
            <div class="step" id="step2">
                <div class="modal-body">
                    <form id="verifyOtpForm" action="verify_otp.php" method="post">
                        <input type="hidden" id="reset-email-hidden" name="email">

                        <div class="form-group">
                            <label for="otp">Enter OTP</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-key"></i>
                                <input type="text" id="otp" name="otp" placeholder="Enter the 6-digit OTP" required
                                    maxlength="6" pattern="[0-9]{6}">
                            </div>
                            <div id="otpTimer">OTP expires in <span id="countdown">05:00</span></div>
                            <div style="text-align: center; margin-top: 5px;">
                                <span class="resend-otp disabled" id="resendOtp">Resend OTP</span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="new-password">New Password</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="new-password" name="new_password"
                                    placeholder="Enter new password" required minlength="8">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm-password">Confirm Password</label>
                            <div class="input-icon-wrapper">
                                <i class="fas fa-lock"></i>
                                <input type="password" id="confirm-password" name="confirm_password"
                                    placeholder="Confirm new password" required minlength="8">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" id="backToEmailBtn">Back</button>
                    <button type="button" class="btn btn-primary" id="resetPasswordBtn">Reset Password</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

    <script src="js/login.js"></script>
    <script>
        // Forgot Password Modal Functionality
        const modal = document.getElementById('forgotPasswordModal');
        const forgotPasswordLink = document.getElementById('forgotPasswordLink');
        const closeModal = document.querySelector('.close-modal');
        const cancelResetBtn = document.getElementById('cancelResetBtn');
        const sendOtpBtn = document.getElementById('sendOtpBtn');
        const backToEmailBtn = document.getElementById('backToEmailBtn');
        const resetPasswordBtn = document.getElementById('resetPasswordBtn');
        const resendOtpBtn = document.getElementById('resendOtp');

        const step1 = document.getElementById('step1');
        const step2 = document.getElementById('step2');

        // Open modal when forgot password link is clicked
        forgotPasswordLink.addEventListener('click', function (e) {
            e.preventDefault();
            modal.style.display = 'block';
        });

        // Close modal when close button or cancel button is clicked
        closeModal.addEventListener('click', closePasswordModal);
        cancelResetBtn.addEventListener('click', closePasswordModal);

        // Close modal when clicking outside the modal content
        window.addEventListener('click', function (e) {
            if (e.target === modal) {
                closePasswordModal();
            }
        });

        function closePasswordModal() {
            modal.style.display = 'none';
            resetModalState();
        }

        function resetModalState() {
            document.getElementById('forgotPasswordForm').reset();
            document.getElementById('verifyOtpForm').reset();
            step1.classList.add('active');
            step2.classList.remove('active');
            stopCountdown();
        }

        // Send OTP button click
        sendOtpBtn.addEventListener('click', function () {
            const email = document.getElementById('reset-email').value;
            if (!email) {
                alert('Please enter your email address');
                return;
            }

            // Show loading state
            sendOtpBtn.disabled = true;
            sendOtpBtn.innerHTML = '<span class="spinner"></span> Sending...';

            // Send AJAX request to send OTP
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'reset_password.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                sendOtpBtn.disabled = false;
                sendOtpBtn.innerHTML = 'Send OTP';

                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // Move to step 2
                            step1.classList.remove('active');
                            step2.classList.add('active');

                            // Set email in hidden field
                            document.getElementById('reset-email-hidden').value = email;

                            // Start countdown
                            startCountdown(5 * 60); // 5 minutes

                            // Enable resend button after 30 seconds
                            setTimeout(function () {
                                resendOtpBtn.classList.remove('disabled');
                            }, 30000);
                        } else {
                            alert(response.message || 'Failed to send OTP. Please try again.');
                        }
                    } catch (e) {
                        alert('An error occurred. Please try again.');
                    }
                } else {
                    alert('An error occurred. Please try again.');
                }
            };
            xhr.onerror = function () {
                sendOtpBtn.disabled = false;
                sendOtpBtn.innerHTML = 'Send OTP';
                alert('An error occurred. Please try again.');
            };
            xhr.send('email=' + encodeURIComponent(email));
        });

        // Back button click
        backToEmailBtn.addEventListener('click', function () {
            step2.classList.remove('active');
            step1.classList.add('active');
            stopCountdown();
        });

        // Reset Password button click
        resetPasswordBtn.addEventListener('click', function () {
            const otp = document.getElementById('otp').value;
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;

            if (!otp) {
                alert('Please enter the OTP');
                return;
            }

            if (!newPassword) {
                alert('Please enter a new password');
                return;
            }

            if (newPassword !== confirmPassword) {
                alert('Passwords do not match');
                return;
            }

            if (newPassword.length < 8) {
                alert('Password must be at least 8 characters long');
                return;
            }

            // Show loading state
            resetPasswordBtn.disabled = true;
            resetPasswordBtn.innerHTML = '<span class="spinner"></span> Resetting...';

            // Submit the form
            const form = document.getElementById('verifyOtpForm');
            form.submit();
        });

        // Resend OTP button click
        resendOtpBtn.addEventListener('click', function () {
            if (resendOtpBtn.classList.contains('disabled')) {
                return;
            }

            const email = document.getElementById('reset-email-hidden').value;

            // Disable resend button
            resendOtpBtn.classList.add('disabled');

            // Send AJAX request to resend OTP
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'reset_password.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function () {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // Restart countdown
                            startCountdown(5 * 60); // 5 minutes

                            // Enable resend button after 30 seconds
                            setTimeout(function () {
                                resendOtpBtn.classList.remove('disabled');
                            }, 30000);

                            alert('OTP has been resent to your email');
                        } else {
                            alert(response.message || 'Failed to resend OTP. Please try again.');
                            resendOtpBtn.classList.remove('disabled');
                        }
                    } catch (e) {
                        alert('An error occurred. Please try again.');
                        resendOtpBtn.classList.remove('disabled');
                    }
                } else {
                    alert('An error occurred. Please try again.');
                    resendOtpBtn.classList.remove('disabled');
                }
            };
            xhr.onerror = function () {
                alert('An error occurred. Please try again.');
                resendOtpBtn.classList.remove('disabled');
            };
            xhr.send('email=' + encodeURIComponent(email) + '&resend=1');
        });

        // Countdown timer functionality
        let countdownInterval;

        function startCountdown(seconds) {
            // Clear any existing countdown
            stopCountdown();

            const countdownElement = document.getElementById('countdown');
            updateCountdownDisplay(seconds, countdownElement);

            countdownInterval = setInterval(function () {
                seconds--;

                if (seconds <= 0) {
                    stopCountdown();
                    countdownElement.textContent = '00:00';
                    alert('OTP has expired. Please request a new one.');
                    step2.classList.remove('active');
                    step1.classList.add('active');
                } else {
                    updateCountdownDisplay(seconds, countdownElement);
                }
            }, 1000);
        }

        function stopCountdown() {
            clearInterval(countdownInterval);
        }

        function updateCountdownDisplay(seconds, element) {
            const minutes = Math.floor(seconds / 60);
            const remainingSeconds = seconds % 60;

            element.textContent =
                (minutes < 10 ? '0' : '') + minutes + ':' +
                (remainingSeconds < 10 ? '0' : '') + remainingSeconds;
        }
    </script>
</body>

</html>