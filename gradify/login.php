<?php
require_once 'database.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$admin_mode = isset($_GET['admin']) && $_GET['admin'] == 1;

// Show database setup message if exists
if (isset($_SESSION['database_setup'])) {
    echo '<div class="alert success" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">' . $_SESSION['database_setup'] . '</div>';
    unset($_SESSION['database_setup']);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // Show success message
            $success_msg = 'Login successful!';
            if ($user['role'] == 'admin') {
                $success_msg .= ' Welcome, Administrator!';
            }
            
            echo '<div class="alert success" style="position: fixed; top: 20px; right: 20px; z-index: 9999;">' . $success_msg . '</div>';
            
            // Redirect after 1 second
            echo '<script>setTimeout(function() { window.location.href = "dashboard.php"; }, 1000);</script>';
            exit;
        } else {
            $error = 'Invalid email or password';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $admin_mode ? 'Admin Login' : 'Login'; ?> - Gradify</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .login-type-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            justify-content: center;
        }
        
        .login-tab {
            padding: 1rem 2rem;
            border: 2px solid var(--light-gray);
            background: white;
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--gray);
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .login-tab:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }
        
        .login-tab.active {
            background: var(--gradient-primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .default-creds {
            background: #f0f9ff;
            border-left: 4px solid #0ea5e9;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-size: 0.9rem;
        }
        
        .default-creds h4 {
            margin: 0 0 10px 0;
            color: #0369a1;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="auth-box">
            <!-- Login Type Tabs -->
            <div class="login-type-tabs">
                <a href="login.php" class="login-tab <?php echo !$admin_mode ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i> Student Login
                </a>
                <a href="login.php?admin=1" class="login-tab <?php echo $admin_mode ? 'active' : ''; ?>">
                    <i class="fas fa-user-shield"></i> Admin Login
                </a>
            </div>
            
            <?php if($admin_mode): ?>
                <h2>Administrator Login</h2>
                <p style="text-align: center; color: var(--gray); margin-bottom: 1.5rem;">Access the admin dashboard</p>
            <?php else: ?>
                <h2>Login to Your Account</h2>
            <p class="auth-link" style="text-align:center;margin-top:10px;">
                <a href="change_password.php">Forgot Password</a>
            </p>
            <?php endif; ?>
            
            <!-- Default Credentials -->
            <?php if(!isset($_COOKIE['hide_creds'])): ?>
            <div class="default-creds">
                <h4><i class="fas fa-key"></i> Default Login Credentials:</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <div>
                        <strong>Admin Account:</strong><br>
                        Email: admin@gmail.com<br>
                        Password: admin456
                    </div>
                    <div>
                        <strong>Student Account:</strong><br>
                        Email: student@gradify.com<br>
                        Password: user123
                    </div>
                </div>
                <button onclick="hideCredentials()" style="margin-top: 10px; background: none; border: none; color: #64748b; cursor: pointer; font-size: 0.85rem;">
                    <i class="fas fa-times"></i> Hide this message
                </button>
            </div>
            <?php endif; ?>
            
            <?php if($error): ?>
                <div class="alert error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required 
                           placeholder="Enter your email address">
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Enter your password">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> 
                    <?php echo $admin_mode ? 'Login as Admin' : 'Login to Account'; ?>
                </button>
            </form>
            
            <p class="auth-link">
                <?php if($admin_mode): ?>
                    <a href="login.php">← Back to Student Login</a>
                <?php else: ?>
                    Don't have an account? <a href="register.php">Register here</a>
                <?php endif; ?>
            </p>
        </div>
    </div>
    
    <script>
        // Hide credentials message
        function hideCredentials() {
            document.querySelector('.default-creds').style.display = 'none';
            // Set cookie to remember for 30 days
            document.cookie = "hide_creds=true; max-age=" + 60*60*24*30 + "; path=/";
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>