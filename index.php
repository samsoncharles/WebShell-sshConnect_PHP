<?php
session_start();

// Redirect if already logged in
if (isset($_SESSION['ssh_connected'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['host'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    try {
        $connection = @ssh2_connect($host, 22);
        if ($connection && @ssh2_auth_password($connection, $username, $password)) {
            $_SESSION['ssh_connected'] = true;
            $_SESSION['ssh_host'] = $host;
            $_SESSION['ssh_user'] = $username;
            $_SESSION['ssh_pass'] = $password;
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid SSH credentials or connection failed';
        }
    } catch (Exception $e) {
        $error = 'SSH connection error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSH Monitor - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="styles.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        /* Loading spinner styles */
        .btn-login {
            position: relative;
        }
        
        .btn-login .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }
        
        .btn-login.loading .spinner {
            display: inline-block;
        }
        
        .btn-login.loading .button-text {
            opacity: 0.5;
        }
        
        @keyframes spin {
            to { transform: translateY(-50%) rotate(360deg); }
        }
        
        /* Error message styles */
        .error-message {
            color: #ff6b6b;
            margin-top: 10px;
            font-size: 0.9rem;
        }
        
        /* Existing styles remain the same */
        .login-page {
            background: linear-gradient(135deg, var(--body-bg), #16213e);
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        /* ... (rest of your existing CSS styles) ... */
    </style>
</head>
<body class="login-page">
    <div class="login-container animate__animated animate__fadeIn">
        <div class="login-card">
            <div class="login-header">
                <i class="bi bi-shield-lock"></i>
                <h1>SSH Login</h1>
                <h3>View linux machine details</h3>
                <h5>(read_only)</h5>
            </div>
            
            <div id="errorContainer">
                <?php if ($error): ?>
                <div class="alert alert-danger animate__animated animate__shakeX">
                    <?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>
            </div>
            
            <form method="POST" class="login-form" id="loginForm">
                <div class="form-group animate__animated animate__fadeInUp">
                    <label for="host"><i class="bi bi-server"></i> Host/IP</label>
                    <input type="text" id="host" name="host" required class="form-control" placeholder="192.168.1.100">
                </div>
                
                <div class="form-group animate__animated animate__fadeInUp animate__delay-1s">
                    <label for="username"><i class="bi bi-person"></i> Username</label>
                    <input type="text" id="username" name="username" required class="form-control" placeholder="root">
                </div>
                
                <div class="form-group animate__animated animate__fadeInUp animate__delay-2s">
                    <label for="password"><i class="bi bi-key"></i> Password</label>
                    <input type="password" id="password" name="password" required class="form-control" placeholder="••••••••">
                </div>
                
                <button type="submit" class="btn-login animate__animated animate__fadeInUp animate__delay-3s" id="loginButton">
                    <span class="button-text"><i class="bi bi-plug"></i> Connect via SSH</span>
                    <span class="spinner"></span>
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginButton = document.getElementById('loginButton');
            const errorContainer = document.getElementById('errorContainer');
            
            // Clear previous errors
            errorContainer.innerHTML = '';
            
            // Show loading state
            loginButton.classList.add('loading');
            loginButton.disabled = true;
            
            // If this is a PHP form submission (not AJAX), the page will reload
            // and either show an error or redirect on success
            // For AJAX implementation, we would need additional JavaScript
        });
        
        // If there's an error, ensure the button returns to normal state
        <?php if ($error): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const loginButton = document.getElementById('loginButton');
            loginButton.classList.remove('loading');
            loginButton.disabled = false;
        });
        <?php endif; ?>
    </script>
</body>
</html>
