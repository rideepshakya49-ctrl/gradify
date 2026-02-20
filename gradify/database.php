<?php
session_start();

// Database configuration
$host = 'localhost';
$dbname = 'gradify_simple';
$username = 'root';
$password = ''; // Try 'root' if empty doesn't work

try {
    // Try to connect to MySQL server
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname 
                CHARACTER SET utf8mb4 
                COLLATE utf8mb4_general_ci");
    
    // Now connect to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT PRIMARY KEY AUTO_INCREMENT,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('user', 'admin') DEFAULT 'user',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create study_sessions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS study_sessions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            title VARCHAR(100) NOT NULL,
            subject VARCHAR(50) NOT NULL,
            description TEXT,
            session_date DATE NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create routine_activities table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS routine_activities (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
            activity VARCHAR(150) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Create timer_sessions table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS timer_sessions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            mode ENUM('pomodoro', 'short_break', 'long_break') NOT NULL,
            duration_minutes INT NOT NULL,
            subject VARCHAR(100) NULL,
            completed_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // Check if we need to insert default users
    $check = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $check->fetch(PDO::FETCH_ASSOC);
    
    if ($result['count'] == 0) {
        // Insert default admin user (password: admin456)
        $admin_password = 'admin456';
        $admin_hash = password_hash($admin_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) 
                              VALUES (?, ?, ?, 'admin')");
        $stmt->execute(['admin', 'admin@gmail.com', $admin_hash]);
        
        // Insert default student user (password: user123)
        $student_password = 'user123';
        $student_hash = password_hash($student_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) 
                              VALUES (?, ?, ?)");
        $stmt->execute(['student', 'student@gradify.com', $student_hash]);
        
        // Insert sample data for demonstration
        // Sample study sessions
        $stmt = $pdo->prepare("INSERT INTO study_sessions (user_id, title, subject, description, session_date, start_time, end_time, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Today's session
        $stmt->execute([2, 'Mathematics Review', 'Mathematics', 'Review calculus chapter 3', 
                       date('Y-m-d'), '09:00:00', '11:00:00', 'completed']);
        
        // Tomorrow's session
        $stmt->execute([2, 'Physics Problems', 'Physics', 'Solve kinematics problems', 
                       date('Y-m-d', strtotime('+1 day')), '14:00:00', '16:00:00', 'pending']);
        
        // Sample routine activities
        $stmt = $pdo->prepare("INSERT INTO routine_activities (user_id, day_of_week, activity, start_time, end_time) 
                              VALUES (?, ?, ?, ?, ?)");
        
        $stmt->execute([2, 'Monday', 'Math Practice', '09:00:00', '10:00:00']);
        $stmt->execute([2, 'Tuesday', 'Physics Study', '14:00:00', '15:30:00']);
        $stmt->execute([2, 'Wednesday', 'English Literature', '10:00:00', '11:30:00']);
        
        // Sample timer sessions
        $stmt = $pdo->prepare("INSERT INTO timer_sessions (user_id, mode, duration_minutes, subject, completed_at) 
                              VALUES (?, ?, ?, ?, ?)");
        
        $stmt->execute([2, 'pomodoro', 25, 'Mathematics', date('Y-m-d H:i:s', strtotime('-2 hours'))]);
        $stmt->execute([2, 'short_break', 5, null, date('Y-m-d H:i:s', strtotime('-1 hour 55 minutes'))]);
        $stmt->execute([2, 'pomodoro', 25, 'Physics', date('Y-m-d H:i:s', strtotime('-1 hour 30 minutes'))]);
        
        // Set session flag for success message
        $_SESSION['database_setup'] = 'Database setup complete! Default users created. Admin: admin@gmail.com/admin456, Student: student@gradify.com/user123';
    }
    
} catch (PDOException $e) {
    // Show simple error
    die("
        <div style='padding: 2rem; font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;'>
            <h2 style='color: #e53e3e;'>Database Connection Error</h2>
            <p style='background: #fff5f5; padding: 1rem; border-radius: 8px; border-left: 4px solid #f56565;'>
                <strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "
            </p>
            
            <div style='background: #f7fafc; padding: 1.5rem; border-radius: 8px; margin-top: 1.5rem;'>
                <h3 style='color: #2d3748; margin-top: 0;'>Troubleshooting Steps:</h3>
                <ol style='color: #4a5568;'>
                    <li>Make sure XAMPP is running</li>
                    <li>Start Apache and MySQL from XAMPP Control Panel</li>
                    <li>If using password, try 'root' as password</li>
                    <li>Check if MySQL service is running on port 3306</li>
                </ol>
            </div>
            
            <div style='margin-top: 2rem;'>
                <a href='index.php' style='background: #667eea; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-block;'>
                    Try Again
                </a>
            </div>
        </div>
    ");
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
?>