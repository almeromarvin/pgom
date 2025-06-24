<?php
require_once 'config/database.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize input
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    // Basic validation
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit();
    }
    
    // Sanitize username (only allow alphanumeric and common characters)
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) {
        echo json_encode(['success' => false, 'message' => 'Invalid username format']);
        exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['username'] = $user['username'];
        
        $redirect_url = $user['role'] == 'admin' ? 'admin/dashboard.php' : 'user/dashboard.php';
        echo json_encode(['success' => true, 'redirect' => $redirect_url, 'role' => $user['role']]);
        exit();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PGOM Facilities</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #198754;
            --primary-dark: #0f5132;
            --primary-light: #e8f5e9;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }

        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 700;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            color: var(--primary-dark);
            transform: scale(1.02);
        }

        .navbar-brand img {
            height: 50px;
            width: auto;
            transition: all 0.3s ease;
        }

        .navbar-brand span {
            font-size: 1.5rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .nav-link {
            color: #344767;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }

        .nav-link:hover {
            color: var(--primary-color);
            background-color: var(--primary-light);
            border-radius: 8px;
        }

        .nav-link.active {
            color: var(--primary-color);
            background-color: var(--primary-light);
            border-radius: 8px;
        }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        .spinner-container {
            position: relative;
            width: 100px;
            height: 100px;
        }

        .spinner {
            position: absolute;
            width: 100px;
            height: 100px;
            border: 8px solid transparent;
            border-top: 8px solid #198754;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .spinner-inner {
            position: absolute;
            width: 80px;
            height: 80px;
            border: 8px solid transparent;
            border-top: 8px solid #ffffff;
            border-radius: 50%;
            animation: spin-reverse 0.8s linear infinite;
            top: 10px;
            left: 10px;
        }

        .spinner-pulse {
            position: absolute;
            width: 100%;
            height: 100%;
            background: rgba(25, 135, 84, 0.2);
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }

        .loading-text {
            color: #ffffff;
            font-size: 24px;
            margin-top: 20px;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(25, 135, 84, 0.5);
            opacity: 0.9;
            animation: text-fade 2s ease-in-out infinite;
        }

        .role-text {
            color: #198754;
            font-size: 20px;
            margin-top: 10px;
            font-weight: bold;
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
            opacity: 0;
            transform: translateY(20px);
            animation: role-reveal 0.5s ease-out forwards 1s;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes spin-reverse {
            0% { transform: rotate(360deg); }
            100% { transform: rotate(0deg); }
        }

        @keyframes pulse {
            0% { transform: scale(0.8); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 0.8; }
            100% { transform: scale(0.8); opacity: 0.5; }
        }

        @keyframes text-fade {
            0%, 100% { opacity: 0.7; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.05); }
        }

        @keyframes role-reveal {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-animation {
            animation: shake 0.5s ease-in-out, fade-out 4s ease-in-out forwards;
        }

        .error-text {
            color: #dc3545;
            font-size: 18px;
            text-align: center;
            margin-top: 15px;
            font-weight: bold;
            animation: error-fade-in 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        @keyframes error-fade-in {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes fade-out {
            0%, 80% { opacity: 1; }
            100% { opacity: 0; }
        }

        /* Login Container Styles */
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 2.5rem;
            width: 100%;
            max-width: 450px;
            margin: 0 auto;
            transition: all 0.3s ease;
        }

        .login-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
        }

        .login-container img {
            height: 120px;
            width: auto;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .login-container img:hover {
            transform: scale(1.05);
        }

        .login-container h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 2rem;
            text-align: center;
        }

        /* Form Styles */
        .form-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(25, 135, 84, 0.15);
            background-color: white;
        }

        .form-control::placeholder {
            color: #6c757d;
            opacity: 0.7;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            border: none;
            border-radius: 12px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            min-height: 48px;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(25, 135, 84, 0.3);
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-color) 100%);
        }

        .btn-success:active {
            transform: translateY(0);
        }

        .alert {
            border-radius: 12px;
            border: none;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .navbar {
                padding: 0.75rem 1rem;
                margin-bottom: 0.5rem;
            }
            
            .navbar-brand img {
                height: 40px;
            }
            
            .navbar-brand span {
                font-size: 1.2rem;
            }
            
            .login-container {
                padding: 2rem 1.5rem;
                margin: 1rem;
                border-radius: 16px;
                max-width: 100%;
            }
            
            .login-container img {
                height: 100px;
                margin-bottom: 1rem;
            }
            
            .login-container h2 {
                font-size: 1.75rem;
                margin-bottom: 1.5rem;
            }
            
            .form-control {
                padding: 0.7rem 0.9rem;
                font-size: 0.95rem;
            }
            
            .btn-success {
                padding: 0.7rem 1.2rem;
                font-size: 0.95rem;
                min-height: 44px;
            }
            
            .form-label {
                font-size: 0.9rem;
            }
            
            .loading-text {
                font-size: 20px;
            }
            
            .role-text {
                font-size: 16px;
            }
        }
        
        @media (max-width: 576px) {
            .navbar {
                padding: 0.5rem 0.75rem;
            }
            
            .navbar-brand {
                gap: 0.75rem;
            }
            
            .navbar-brand img {
                height: 35px;
            }
            
            .navbar-brand span {
                font-size: 1rem;
            }
            
            .login-container {
                padding: 1.5rem 1rem;
                margin: 0.5rem;
                border-radius: 12px;
            }
            
            .login-container img {
                height: 80px;
                margin-bottom: 0.75rem;
            }
            
            .login-container h2 {
                font-size: 1.5rem;
                margin-bottom: 1.25rem;
            }
            
            .form-control {
                padding: 0.6rem 0.8rem;
                font-size: 0.9rem;
                border-radius: 10px;
            }
            
            .btn-success {
                padding: 0.6rem 1rem;
                font-size: 0.9rem;
                min-height: 42px;
                border-radius: 10px;
            }
            
            .form-label {
                font-size: 0.85rem;
                margin-bottom: 0.4rem;
            }
            
            .loading-text {
                font-size: 18px;
                margin-top: 15px;
            }
            
            .role-text {
                font-size: 14px;
                margin-top: 8px;
            }
            
            .spinner-container {
                width: 80px;
                height: 80px;
            }
            
            .spinner {
                width: 80px;
                height: 80px;
                border-width: 6px;
            }
            
            .spinner-inner {
                width: 64px;
                height: 64px;
                border-width: 6px;
                top: 8px;
                left: 8px;
            }
        }
        
        @media (max-width: 480px) {
            .navbar {
                padding: 0.4rem 0.5rem;
            }
            
            .navbar-brand img {
                height: 30px;
            }
            
            .navbar-brand span {
                font-size: 0.9rem;
            }
            
            .login-container {
                padding: 1.25rem 0.75rem;
                margin: 0.25rem;
                border-radius: 10px;
            }
            
            .login-container img {
                height: 70px;
                margin-bottom: 0.5rem;
            }
            
            .login-container h2 {
                font-size: 1.25rem;
                margin-bottom: 1rem;
            }
            
            .form-control {
                padding: 0.5rem 0.7rem;
                font-size: 0.85rem;
                border-radius: 8px;
            }
            
            .btn-success {
                padding: 0.5rem 0.8rem;
                font-size: 0.85rem;
                min-height: 40px;
                border-radius: 8px;
            }
            
            .form-label {
                font-size: 0.8rem;
                margin-bottom: 0.3rem;
            }
            
            .loading-text {
                font-size: 16px;
                margin-top: 12px;
            }
            
            .role-text {
                font-size: 12px;
                margin-top: 6px;
            }
        }
        
        @media (max-width: 360px) {
            .navbar-brand span {
                font-size: 0.8rem;
            }
            
            .login-container {
                padding: 1rem 0.5rem;
            }
            
            .login-container img {
                height: 60px;
            }
            
            .login-container h2 {
                font-size: 1.1rem;
            }
            
            .form-control {
                padding: 0.4rem 0.6rem;
                font-size: 0.8rem;
            }
            
            .btn-success {
                padding: 0.4rem 0.7rem;
                font-size: 0.8rem;
                min-height: 38px;
            }
        }
        
        /* Landscape orientation for mobile */
        @media (max-height: 500px) and (orientation: landscape) {
            .navbar {
                padding: 0.3rem 0.5rem;
                margin-bottom: 0.25rem;
            }
            
            .navbar-brand img {
                height: 25px;
            }
            
            .navbar-brand span {
                font-size: 0.8rem;
            }
            
            .login-container {
                padding: 1rem 1.5rem;
                margin: 0.25rem;
            }
            
            .login-container img {
                height: 50px;
                margin-bottom: 0.5rem;
            }
            
            .login-container h2 {
                font-size: 1.1rem;
                margin-bottom: 0.75rem;
            }
            
            .form-label {
                margin-bottom: 0.2rem;
            }
            
            .mb-3 {
                margin-bottom: 0.75rem !important;
            }
            
            .mb-4 {
                margin-bottom: 1rem !important;
            }
        }
        
        /* Touch-friendly improvements */
        .btn, .form-control {
            min-height: 44px;
        }
        
        /* Prevent zoom on input focus (iOS) */
        @media screen and (-webkit-min-device-pixel-ratio: 0) {
            .form-control {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <div class="navbar-brand">
                <img src="images/logo.png" alt="PGOM Logo">
                <span>PGOM FACILITIES</span>
            </div>
        </div>
    </nav>

    <div class="loading-overlay">
        <div class="spinner-container">
            <div class="spinner-pulse"></div>
            <div class="spinner"></div>
            <div class="spinner-inner"></div>
        </div>
        <div class="loading-text">Authenticating</div>
        <div class="role-text"></div>
    </div>

    <div class="container-fluid d-flex align-items-center justify-content-center" style="min-height: calc(100vh - 80px); padding: 1rem;">
        <div class="login-container">
            <div class="text-center mb-4">
                <img src="images/logo.png" alt="PGOM Logo" class="mb-3">
                <h2>Login</h2>
            </div>
            <div class="alert alert-danger" id="error-message" style="display: none;"></div>
            
            <form id="login-form" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Enter your username" required>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Login
                    </button>
                </div>
            </form>
            <div class="text-center mt-3">
                <a href="forgot_password.php" class="forgot-password-link">
                    <i class="bi bi-question-circle me-1"></i>Forgot Password?
                </a>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('login-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const form = this;
        const errorMessage = document.getElementById('error-message');
        const loadingOverlay = document.querySelector('.loading-overlay');
        const formData = new FormData(form);

        fetch('login.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadingOverlay.style.display = 'flex';
                const loadingText = document.querySelector('.loading-text');
                const roleText = document.querySelector('.role-text');

                setTimeout(() => {
                    loadingText.textContent = 'Welcome';
                    setTimeout(() => {
                        roleText.textContent = `Logging in as ${data.role === 'admin' ? 'Administrator' : 'User'}`;
                        setTimeout(() => {
                            window.location.href = data.redirect;
                        }, 1500);
                    }, 1500);
                }, 2000);
            } else {
                errorMessage.textContent = data.message;
                errorMessage.style.display = 'block';
                errorMessage.classList.add('error-animation');
                setTimeout(() => {
                    errorMessage.classList.remove('error-animation');
                    errorMessage.style.display = 'none';
                }, 4000);
            }
        })
        .catch(error => {
            errorMessage.textContent = 'An error occurred. Please try again.';
            errorMessage.style.display = 'block';
        });
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>