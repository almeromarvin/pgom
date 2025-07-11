<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .forgot-container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08);
            padding: 2.5rem 2rem;
            max-width: 400px;
            width: 100%;
        }

        .forgot-title {
            color: #198754;
            font-weight: 700;
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 500;
        }

        .btn-success {
            background: linear-gradient(135deg, #198754 0%, #0f5132 100%);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            min-height: 48px;
        }

        .btn-success:hover {
            background: linear-gradient(135deg, #0f5132 0%, #198754 100%);
        }

        .back-link {
            display: inline-block;
            margin-top: 1.5rem;
            color: #198754;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            color: #0f5132;
            text-decoration: underline;
        }

        /* Resend button styling */
        .btn-outline-success {
            border-color: #198754;
            color: #198754;
            transition: all 0.3s ease;
        }

        .btn-outline-success:hover {
            background-color: #198754;
            border-color: #198754;
            color: white;
            transform: translateY(-1px);
        }

        .btn-outline-success:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        #resendTimer {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 8px 12px;
            border: 1px solid #e9ecef;
        }

        #countdown {
            font-weight: 600;
            color: #198754;
        }
    </style>
</head>

<body>
    <div class="forgot-container">
        <div class="text-center mb-4">
            <i class="bi bi-shield-lock" style="font-size: 2.5rem; color: #198754;"></i>
            <h2 class="forgot-title">Forgot Password</h2>
            <p class="text-muted">Enter your email address to receive a password reset code.</p>
        </div>
        <!-- Step 1: Email Form -->
        <form id="forgotPasswordForm">
            <div class="mb-3">
                <label for="resetEmail" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="resetEmail" name="email" placeholder="Enter your email address" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-send me-2"></i>Send Reset Code
                </button>
            </div>
        </form>
        <!-- Step 2: Code Verification Form -->
        <form id="verifyCodeForm" style="display:none;">
            <div class="mb-3 text-center">
                <label for="verificationCode1" class="form-label">Verification Code</label>
                <div style="display: flex; gap: 0.5rem; justify-content: center;">
                    <input type="text" class="form-control code-input" id="verificationCode1" maxlength="1" inputmode="numeric" pattern="[0-9]*" required style="width: 2.5rem; text-align: center; font-size: 1.5rem;" autocomplete="one-time-code">
                    <input type="text" class="form-control code-input" id="verificationCode2" maxlength="1" inputmode="numeric" pattern="[0-9]*" required style="width: 2.5rem; text-align: center; font-size: 1.5rem;">
                    <input type="text" class="form-control code-input" id="verificationCode3" maxlength="1" inputmode="numeric" pattern="[0-9]*" required style="width: 2.5rem; text-align: center; font-size: 1.5rem;">
                    <input type="text" class="form-control code-input" id="verificationCode4" maxlength="1" inputmode="numeric" pattern="[0-9]*" required style="width: 2.5rem; text-align: center; font-size: 1.5rem;">
                    <input type="text" class="form-control code-input" id="verificationCode5" maxlength="1" inputmode="numeric" pattern="[0-9]*" required style="width: 2.5rem; text-align: center; font-size: 1.5rem;">
                    <input type="text" class="form-control code-input" id="verificationCode6" maxlength="1" inputmode="numeric" pattern="[0-9]*" required style="width: 2.5rem; text-align: center; font-size: 1.5rem;">
                </div>
                <div class="mt-3">
                    <p class="text-muted small">Didn't receive the code?</p>
                    <button type="button" id="resendCodeBtn" class="btn btn-outline-success btn-sm">
                        <i class="bi bi-arrow-clockwise me-1"></i>Resend Code
                    </button>
                    <div id="resendTimer" class="mt-2" style="display:none;">
                        <small class="text-muted">Resend available in <span id="countdown">60</span> seconds</small>
                    </div>
                </div>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-check-circle me-2"></i>Verify Code
                </button>
            </div>
        </form>
        <!-- Step 3: New Password Form -->
        <form id="newPasswordForm" style="display:none;">
            <div class="mb-3">
                <label for="newPassword" class="form-label">New Password</label>
                <input type="password" class="form-control" id="newPassword" name="password" placeholder="Enter new password" required>
            </div>
            <div class="mb-3">
                <label for="confirmPassword" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirmPassword" name="confirm_password" placeholder="Confirm new password" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-success">
                    <i class="bi bi-shield-check me-2"></i>Reset Password
                </button>
            </div>
        </form>
        <a href="login.php" class="back-link"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
        <div id="message" class="mt-3"></div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const email = document.getElementById('resetEmail').value.trim();

            // Validate email format
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                document.getElementById('message').innerHTML = `<div class='alert alert-danger'><i class='bi bi-x-circle me-2'></i>Please enter a valid email address.</div>`;
                return;
            }

            userEmail = email;
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending...';
            submitBtn.disabled = true;
            $.ajax({
                url: 'forgot_password_backend.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    email: email,
                    send_code: true
                },
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            showConfirmButton: false,
                            title: response.message,
                            timer: 1000
                        }).then(() => {
                            location.href = "login.php"
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            timer: 1200,
                            showConfirmButton: false,
                            title: response.message
                        }).then(() => {
                            location.href = 'login.php';
                        });
                    }
                },
                error: function(xhr) {
                    console.log(xhr.responseText);
                }
            })
        });
        // Code input auto-advance and backspace
    </script>
</body>

</html>