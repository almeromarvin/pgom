<?php

if (!isset($_GET['token']) || empty($_GET['token']) || is_null($_GET['token'])) {
    header("Location: login.php");
}

?>

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
            <p class="text-muted">Enter your new password</p>
        </div>
        <!-- Step 1: Email Form -->
        <form id="forgotPasswordForm">
            <div class="mb-3">
                <label for="resetEmail" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Enter your email address" required>
            </div>

            <div class="mb-3">
                <label for="resetEmail" class="form-label">Confirm Password</label>
                <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" placeholder="Enter your email address" required>
            </div>
            <div class="d-grid">
                <button type="submit" class="btn btn-success" id="resetBTN">
                    <i class="bi bi-send me-2"></i>Reset Password
                </button>
            </div>
        </form>
        <a href="login.php" class="back-link"><i class="bi bi-arrow-left me-1"></i>Back to Login</a>
        <div id="message" class="mt-3"></div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.getElementById('resetBTN').addEventListener('click', function(e) {
            e.preventDefault();

            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirmPassword');

            var formData = new FormData(document.querySelector('form'));
            formData.append('resetPassword', true);

            if (confirmPassword.value !== password.value) {
                password.classList.add('border-danger');
                confirmPassword.classList.add('border-danger');
            } else {
                password.classList.remove('border-danger');
                confirmPassword.classList.remove('border-danger');


                $.ajax({
                    url: 'forgot_password_backend.php?token=<?= $_GET['token'] ?>',
                    type: 'POST',
                    dataType: 'json',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        console.log(response);

                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                timer: 1200,
                                showConfirmButton: false,
                                title: response.message
                            }).then(() => {
                                location.href = 'login.php';
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

            }
        });
    </script>

</body>