<?php
require_once 'database.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('login.php');
}

$message = '';

// Handles the action to be delete
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_session'])) {
        $stmt = $pdo->prepare("DELETE FROM study_sessions WHERE id = ?");
        $stmt->execute([$_POST['session_id']]);
        $message = '<div class="alert success">Study plan deleted!</div>';
    }

    if (isset($_POST['delete_routine'])) {
        $stmt = $pdo->prepare("DELETE FROM routine_activities WHERE id = ?");
        $stmt->execute([$_POST['routine_id']]);
        $message = '<div class="alert success">Routine activity deleted!</div>';
    }

    if (isset($_POST['delete_timer'])) {
        $stmt = $pdo->prepare("DELETE FROM timer_sessions WHERE id = ?");
        $stmt->execute([$_POST['timer_id']]);
        $message = '<div class="alert success">Timer session deleted!</div>';
    }

    if (isset($_POST['delete_user'])) {
        $userId = $_POST['user_id'];
        // Don't allow deleting yourself
        if ($userId != $_SESSION['user_id']) {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $message = '<div class="alert success">User deleted!</div>';
        } else {
            $message = '<div class="alert error">You cannot delete your own account!</div>';
        }
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

//for all study sessions with user info
$sessions = $pdo->query("SELECT ss.*, u.username as user_name FROM study_sessions ss 
                         LEFT JOIN users u ON ss.user_id = u.id 
                         ORDER BY ss.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// for all routine activities with user info
$routines = $pdo->query("SELECT ra.*, u.username as user_name FROM routine_activities ra 
                         LEFT JOIN users u ON ra.user_id = u.id 
                         ORDER BY ra.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// for all timer sessions with user info
$timers = $pdo->query("SELECT ts.*, u.username as user_name FROM timer_sessions ts 
                       LEFT JOIN users u ON ts.user_id = u.id 
                       ORDER BY ts.completed_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - Gradify</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="dashboard-hero" style="text-align: center;">
            <h1><i class="fas fa-user-shield"></i> Admin Dashboard</h1>
            <p>Manage users, study plans, routines, and timer sessions</p>
        </div>
        
        <?php echo $message; ?>
        
        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-users stat-icon"></i>
                <p class="stat-number"><?php echo count($users); ?></p>
                <p class="stat-label">Total Users</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-calendar-alt stat-icon"></i>
                <p class="stat-number"><?php echo count($sessions); ?></p>
                <p class="stat-label">Study Plans</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-tasks stat-icon"></i>
                <p class="stat-number"><?php echo count($routines); ?></p>
                <p class="stat-label">Routine Activities</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-clock stat-icon"></i>
                <p class="stat-number"><?php echo count($timers); ?></p>
                <p class="stat-label">Timer Sessions</p>
            </div>
        </div>
        
        <!-- for users Management -->
        <div class="section">
            <div class="section-header">
                <h2>Users Management (<?php echo count($users); ?>)</h2>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($user['username']); ?>
                                <?php if($user['id'] == $_SESSION['user_id']): ?>
                                    <span class="status-badge status-completed" style="font-size: 0.7rem;">You</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <span class="status-badge <?php echo $user['role'] == 'admin' ? 'status-completed' : 'status-pending'; ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                            <td>
                                <?php if($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="delete_user" class="btn-small btn-danger" 
                                            onclick="return confirm('Delete user <?php echo htmlspecialchars($user['username']); ?>? This will also delete all their data.')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- for Study Plans Management -->
        <div class="section">
            <div class="section-header">
                <h2>Study Plans (<?php echo count($sessions); ?>)</h2>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Title</th>
                            <th>Subject</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($sessions as $session): ?>
                        <tr>
                            <td><?php echo $session['id']; ?></td>
                            <td><?php echo htmlspecialchars($session['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($session['title']); ?></td>
                            <td><?php echo htmlspecialchars($session['subject']); ?></td>
                            <td>
                                <?php echo date('M d, Y', strtotime($session['session_date'])); ?><br>
                                <?php echo date('g:i A', strtotime($session['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($session['end_time'])); ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $session['status']; ?>">
                                    <?php echo ucfirst($session['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($session['created_at'])); ?></td>
                            <td>
                                <a href="plans.php?action=edit&id=<?php echo $session['id']; ?>" class="btn-small" style="margin-bottom: 5px;">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="session_id" value="<?php echo $session['id']; ?>">
                                    <button type="submit" name="delete_session" class="btn-small btn-danger" 
                                            onclick="return confirm('Delete this study plan?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- for routines Management -->
        <div class="section">
            <div class="section-header">
                <h2>Routine Activities (<?php echo count($routines); ?>)</h2>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Day</th>
                            <th>Activity</th>
                            <th>Time</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($routines as $routine): ?>
                        <tr>
                            <td><?php echo $routine['id']; ?></td>
                            <td><?php echo htmlspecialchars($routine['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($routine['day_of_week']); ?></td>
                            <td><?php echo htmlspecialchars($routine['activity']); ?></td>
                            <td>
                                <?php echo date('g:i A', strtotime($routine['start_time'])); ?> - 
                                <?php echo date('g:i A', strtotime($routine['end_time'])); ?>
                            </td>
                            <td><?php echo date('M d, Y', strtotime($routine['created_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="routine_id" value="<?php echo $routine['id']; ?>">
                                    <button type="submit" name="delete_routine" class="btn-small btn-danger" 
                                            onclick="return confirm('Delete this routine activity?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- for timer Sessions Management -->
        <div class="section">
            <div class="section-header">
                <h2>Timer Sessions (<?php echo count($timers); ?>)</h2>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Mode</th>
                            <th>Duration</th>
                            <th>Subject</th>
                            <th>Completed At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($timers as $timer): ?>
                        <tr>
                            <td><?php echo $timer['id']; ?></td>
                            <td><?php echo htmlspecialchars($timer['user_name']); ?></td>
                            <td>
                                <?php 
                                $modeText = [
                                    'pomodoro' => 'Focus',
                                    'short_break' => 'Short Break',
                                    'long_break' => 'Long Break'
                                ];
                                echo $modeText[$timer['mode']] ?? $timer['mode'];
                                ?>
                            </td>
                            <td><?php echo $timer['duration_minutes']; ?> min</td>
                            <td><?php echo htmlspecialchars($timer['subject'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M d, Y g:i A', strtotime($timer['completed_at'])); ?></td>
                            <td>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="timer_id" value="<?php echo $timer['id']; ?>">
                                    <button type="submit" name="delete_timer" class="btn-small btn-danger" 
                                            onclick="return confirm('Delete this timer session?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-hide alerts
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