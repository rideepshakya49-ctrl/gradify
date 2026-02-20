<?php
require_once 'database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle timer completion
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['complete_timer'])) {
        $mode = $_POST['mode'];
        $duration = $_POST['duration'];
        $subject = isset($_POST['subject']) ? trim($_POST['subject']) : null;
        
        // Save timer session
        $stmt = $pdo->prepare("INSERT INTO timer_sessions (user_id, mode, duration_minutes, subject, completed_at) 
                              VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $mode, $duration, $subject]);
        $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Timer session saved! Great job!</div>';
    }
    
    // Handle custom time settings
    if (isset($_POST['save_custom_times'])) {
        $custom_focus = intval($_POST['custom_focus']);
        $custom_short = intval($_POST['custom_short']);
        $custom_long = intval($_POST['custom_long']);
        
        // Store in session for this user
        $_SESSION['custom_focus'] = $custom_focus;
        $_SESSION['custom_short'] = $custom_short;
        $_SESSION['custom_long'] = $custom_long;
        
        $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Custom times saved!</div>';
    }
    
    // Handle timer session deletion (admin only) - FIXED: changed 'delete_timer_id' to 'timer_id'
    if (isset($_POST['delete_timer']) && isset($_POST['timer_id']) && isAdmin()) {
        $stmt = $pdo->prepare("DELETE FROM timer_sessions WHERE id = ?");
        $stmt->execute([$_POST['timer_id']]);
        $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Timer session deleted!</div>';
    }
}

// Get custom times from session or use defaults
$custom_focus = $_SESSION['custom_focus'] ?? 25;
$custom_short = $_SESSION['custom_short'] ?? 5;
$custom_long = $_SESSION['custom_long'] ?? 15;

// Get timer stats - admin sees all, users see only their own
$today = date('Y-m-d');
if (isAdmin()) {
    // Admin sees all sessions
    $todayStmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(duration_minutes) as total FROM timer_sessions WHERE DATE(completed_at) = ?");
    $todayStmt->execute([$today]);
    $todayStats = $todayStmt->fetch(PDO::FETCH_ASSOC);
    
    $weekStmt = $pdo->prepare("SELECT SUM(duration_minutes) as total FROM timer_sessions WHERE YEARWEEK(completed_at, 1) = YEARWEEK(CURDATE(), 1)");
    $weekStmt->execute();
    $weekStats = $weekStmt->fetch(PDO::FETCH_ASSOC);
    
    $productiveStmt = $pdo->prepare("SELECT DATE(completed_at) as day, SUM(duration_minutes) as total 
                                     FROM timer_sessions 
                                     WHERE completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                     GROUP BY DATE(completed_at) 
                                     ORDER BY total DESC 
                                     LIMIT 1");
    $productiveStmt->execute();
    $productiveDay = $productiveStmt->fetch(PDO::FETCH_ASSOC);
    
    // Admin sees all recent sessions
    $recentSessions = $pdo->prepare("SELECT ts.*, u.username as user_name FROM timer_sessions ts 
                                     LEFT JOIN users u ON ts.user_id = u.id 
                                     ORDER BY ts.completed_at DESC LIMIT 10");
    $recentSessions->execute();
    $sessions = $recentSessions->fetchAll(PDO::FETCH_ASSOC);
} else {
    // User sees only their own sessions
    $todayStmt = $pdo->prepare("SELECT COUNT(*) as count, SUM(duration_minutes) as total FROM timer_sessions WHERE user_id = ? AND DATE(completed_at) = ?");
    $todayStmt->execute([$user_id, $today]);
    $todayStats = $todayStmt->fetch(PDO::FETCH_ASSOC);
    
    $weekStmt = $pdo->prepare("SELECT SUM(duration_minutes) as total FROM timer_sessions WHERE user_id = ? AND YEARWEEK(completed_at, 1) = YEARWEEK(CURDATE(), 1)");
    $weekStmt->execute([$user_id]);
    $weekStats = $weekStmt->fetch(PDO::FETCH_ASSOC);
    
    $productiveStmt = $pdo->prepare("SELECT DATE(completed_at) as day, SUM(duration_minutes) as total 
                                     FROM timer_sessions 
                                     WHERE user_id = ? AND completed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                                     GROUP BY DATE(completed_at) 
                                     ORDER BY total DESC 
                                     LIMIT 1");
    $productiveStmt->execute([$user_id]);
    $productiveDay = $productiveStmt->fetch(PDO::FETCH_ASSOC);
    
    $recentSessions = $pdo->prepare("SELECT * FROM timer_sessions WHERE user_id = ? ORDER BY completed_at DESC LIMIT 10");
    $recentSessions->execute([$user_id]);
    $sessions = $recentSessions->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Focus Timer - Gradify</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .timer-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .timer-stat {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius);
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .timer-stat:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .timer-stat h3 {
            font-size: 1rem;
            color: var(--gray);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .timer-stat p {
            font-size: 2rem;
            font-weight: 800;
            color: var(--primary);
            margin: 0;
        }
        
        .timer-stat small {
            display: block;
            margin-top: 0.5rem;
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        #timerStatus {
            font-size: 1.2rem;
            margin: 1.5rem 0;
            color: var(--dark);
            font-weight: 500;
        }
        
        .completion-message {
            text-align: center;
            margin: 2rem 0;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 0.7; }
            50% { opacity: 1; }
            100% { opacity: 0.7; }
        }
        
        .custom-time-section {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            margin: 2rem 0;
            box-shadow: var(--shadow);
        }
        
        .custom-time-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 1.5rem 0;
        }
        
        .custom-time-input {
            text-align: center;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: var(--radius-sm);
        }
        
        .custom-time-input label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
            font-size: 1.1rem;
        }
        
        .custom-time-input input {
            width: 100px;
            padding: 0.75rem;
            font-size: 1.5rem;
            text-align: center;
            border: 2px solid var(--light-gray);
            border-radius: var(--radius-sm);
            margin: 0 auto;
            display: block;
            font-weight: 600;
        }
        
        .custom-time-input small {
            display: block;
            margin-top: 0.5rem;
            color: var(--gray);
            font-size: 0.85rem;
        }
        
        .quick-presets {
            display: flex;
            gap: 0.5rem;
            margin: 1rem 0;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .preset-btn {
            padding: 0.5rem 1rem;
            background: var(--light);
            border: 2px solid var(--light-gray);
            border-radius: 50px;
            cursor: pointer;
            font-weight: 500;
            color: var(--dark);
            transition: var(--transition);
        }
        
        .preset-btn:hover {
            background: rgba(102, 126, 234, 0.1);
            border-color: var(--primary);
        }
        
        .preset-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
        }
        
        .progress-container {
            margin: 2rem 0;
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--light-gray);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            border-radius: 4px;
            transition: width 1s linear;
        }
        
        .countdown-display {
            position: relative;
            display: inline-block;
        }
        
        .circle-progress {
            width: 300px;
            height: 300px;
            margin: 2rem auto;
            position: relative;
        }
        
        .circle-svg {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }
        
        .circle-bg {
            fill: none;
            stroke: var(--light-gray);
            stroke-width: 8;
        }
        
        .circle-progress-bar {
            fill: none;
            stroke: var(--primary);
            stroke-width: 8;
            stroke-linecap: round;
            transition: stroke-dashoffset 1s linear;
        }
        
        .circle-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
        }
        
        .circle-time {
            font-size: 3.5rem;
            font-weight: 800;
            color: var(--dark);
            font-family: 'Courier New', monospace;
        }
        
        .circle-mode {
            font-size: 1rem;
            color: var(--gray);
            margin-top: 0.5rem;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .circle-progress {
                width: 250px;
                height: 250px;
            }
            
            .circle-time {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .circle-progress {
                width: 200px;
                height: 200px;
            }
            
            .circle-time {
                font-size: 2rem;
            }
        }
        
        .quick-actions-timer {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin: 2rem 0;
            flex-wrap: wrap;
        }
        
        .timer-btn-custom {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        
        .timer-btn-custom.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .timer-btn-custom.primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .timer-btn-custom.secondary {
            background: white;
            color: var(--primary);
            border: 2px solid var(--light-gray);
        }
        
        .timer-btn-custom.secondary:hover {
            background: var(--light);
            transform: translateY(-3px);
        }
        
        .timer-btn-custom.danger {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
        }
        
        .timer-session-list {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            margin: 2rem 0;
            box-shadow: var(--shadow);
        }
        
        .session-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--light-gray);
            transition: var(--transition);
        }
        
        .session-item:hover {
            background: #f8fafc;
        }
        
        .session-item:last-child {
            border-bottom: none;
        }
        
        .session-info h4 {
            margin: 0;
            color: var(--dark);
        }
        
        .session-time {
            color: var(--gray);
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        
        .session-duration {
            background: rgba(102, 126, 234, 0.1);
            color: var(--primary);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .admin-delete-btn {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            margin-left: 10px;
        }
        
        .admin-delete-btn:hover {
            background: #e53e3e;
            transform: scale(1.1);
        }
        
        .timer-container {
            background: white;
            padding: 3rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            margin: 2rem 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .timer-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: var(--gradient-primary);
        }
        
        .mode-selector {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }
        
        .mode-btn {
            padding: 1rem 1.5rem;
            border: 2px solid var(--primary);
            background: white;
            color: var(--primary);
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .mode-btn:hover {
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }
        
        .mode-btn.active {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .timer-controls {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin: 2rem 0;
            flex-wrap: wrap;
        }
        
        .custom-time-input-container {
            display: inline-flex;
            align-items: center;
            gap: 1rem;
            background: white;
            padding: 1rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin: 1.5rem 0;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <?php echo $message; ?>
        
        <!-- Timer Stats -->
        <div class="timer-stats">
            <div class="timer-stat">
                <h3>Today's Focus</h3>
                <p><?php echo $todayStats['total'] ?? 0; ?> min</p>
            </div>
            <div class="timer-stat">
                <h3>Today's Sessions</h3>
                <p><?php echo $todayStats['count'] ?? 0; ?></p>
            </div>
            <div class="timer-stat">
                <h3>This Week</h3>
                <p><?php echo $weekStats['total'] ?? 0; ?> min</p>
            </div>
            <div class="timer-stat">
                <h3>Best Day</h3>
                <p><?php echo $productiveDay['total'] ?? 0; ?> min</p>
                <?php if($productiveDay): ?>
                    <small><?php echo date('D, M j', strtotime($productiveDay['day'])); ?></small>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Timer Container -->
        <div class="timer-container">
            <h2><i class="fas fa-clock"></i> Focus Timer</h2>
            
            <!-- Mode Selector -->
            <div class="mode-selector">
                <button class="mode-btn active" data-minutes="<?php echo $custom_focus; ?>" data-mode="pomodoro">
                    <i class="fas fa-brain"></i> Focus (<?php echo $custom_focus; ?> min)
                </button>
                <button class="mode-btn" data-minutes="<?php echo $custom_short; ?>" data-mode="short_break">
                    <i class="fas fa-coffee"></i> Short Break (<?php echo $custom_short; ?> min)
                </button>
                <button class="mode-btn" data-minutes="<?php echo $custom_long; ?>" data-mode="long_break">
                    <i class="fas fa-couch"></i> Long Break (<?php echo $custom_long; ?> min)
                </button>
            </div>
            
            <!-- Quick Presets -->
            <div class="quick-presets">
                <button class="preset-btn" data-minutes="15">15 min</button>
                <button class="preset-btn" data-minutes="25">25 min</button>
                <button class="preset-btn" data-minutes="30">30 min</button>
                <button class="preset-btn" data-minutes="45">45 min</button>
                <button class="preset-btn" data-minutes="60">60 min</button>
                <button class="preset-btn" data-minutes="90">90 min</button>
            </div>
            
            <!-- Custom Time Input -->
            <div style="text-align: center; margin: 1.5rem 0;">
                <div class="custom-time-input-container">
                    <label for="customTime" style="font-weight: 600; color: var(--dark);">
                        <i class="fas fa-edit"></i> Custom Time:
                    </label>
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <input type="number" id="customTime" min="1" max="240" value="30" 
                               style="width: 80px; padding: 0.5rem; text-align: center; border: 2px solid var(--light-gray); border-radius: var(--radius-sm); font-weight: 600;">
                        <span style="color: var(--gray);">minutes</span>
                    </div>
                    <button id="setCustomTime" class="btn btn-small btn-primary">
                        <i class="fas fa-check"></i> Set
                    </button>
                </div>
            </div>
            
            <!-- Timer Display -->
            <div class="circle-progress">
                <svg class="circle-svg">
                    <circle class="circle-bg" cx="150" cy="150" r="140"></circle>
                    <circle class="circle-progress-bar" cx="150" cy="150" r="140" 
                            stroke-dasharray="879.2" stroke-dashoffset="0"></circle>
                </svg>
                <div class="circle-text">
                    <div class="circle-time" id="timerDisplay"><?php echo sprintf('%02d', $custom_focus); ?>:00</div>
                    <div class="circle-mode" id="timerMode">Focus Session</div>
                    <div id="timerStatus" style="margin: 0.5rem 0; font-size: 1rem;">Ready to focus!</div>
                </div>
            </div>
            
            <!-- Timer Controls -->
            <div class="timer-controls">
                <button class="timer-btn-custom primary" id="startBtn">
                    <i class="fas fa-play"></i> Start Timer
                </button>
                <button class="timer-btn-custom secondary" id="pauseBtn" disabled>
                    <i class="fas fa-pause"></i> Pause
                </button>
                <button class="timer-btn-custom secondary" id="resetBtn">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>
            
            <!-- Progress Bar -->
            <div class="progress-container">
                <div class="progress-label">
                    <span>Time Elapsed</span>
                    <span id="progressText">0%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" id="progressBar"></div>
                </div>
            </div>
        </div>
        
        <!-- Custom Time Settings -->
        <div class="custom-time-section">
            <h3><i class="fas fa-cog"></i> Customize Timer Durations</h3>
            <p style="color: var(--gray); margin-bottom: 1.5rem;">Set your preferred times for each mode</p>
            
            <form method="POST">
                <div class="custom-time-grid">
                    <div class="custom-time-input">
                        <label for="custom_focus">
                            <i class="fas fa-brain" style="color: #667eea;"></i> Focus Time
                        </label>
                        <input type="number" id="custom_focus" name="custom_focus" 
                               min="1" max="240" value="<?php echo $custom_focus; ?>" required>
                        <small>Recommended: 25-50 min</small>
                    </div>
                    
                    <div class="custom-time-input">
                        <label for="custom_short">
                            <i class="fas fa-coffee" style="color: #48bb78;"></i> Short Break
                        </label>
                        <input type="number" id="custom_short" name="custom_short" 
                               min="1" max="60" value="<?php echo $custom_short; ?>" required>
                        <small>Recommended: 5-10 min</small>
                    </div>
                    
                    <div class="custom-time-input">
                        <label for="custom_long">
                            <i class="fas fa-couch" style="color: #ed8936;"></i> Long Break
                        </label>
                        <input type="number" id="custom_long" name="custom_long" 
                               min="5" max="60" value="<?php echo $custom_long; ?>" required>
                        <small>Recommended: 15-30 min</small>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 2rem;">
                    <button type="submit" name="save_custom_times" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Custom Times
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Timer Completion Form -->
        <form method="POST" class="form-container" id="timerForm" style="display: none;">
            <div class="completion-message">
                <i class="fas fa-trophy" style="font-size: 3rem; color: var(--success); margin-bottom: 1rem;"></i>
                <h3>Great Work! Session Complete! 🎉</h3>
                <p>Take a moment to reflect on what you accomplished</p>
            </div>
            
            <input type="hidden" name="mode" id="completedMode" value="">
            <input type="hidden" name="duration" id="completedDuration" value="">
            <input type="hidden" name="complete_timer" value="1">
            
            <div class="form-group">
                <label><i class="fas fa-book"></i> What did you study? (Optional)</label>
                <input type="text" name="subject" placeholder="e.g., Mathematics Chapter 3, Physics Problems, English Literature...">
                <small style="color: var(--gray); display: block; margin-top: 0.5rem;">This helps track your study patterns</small>
            </div>
            
            <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Session
                </button>
                <button type="button" class="btn btn-secondary" onclick="resetTimer()">
                    <i class="fas fa-redo"></i> Start New Session
                </button>
            </div>
        </form>
        
        <!-- Recent Timer Sessions -->
        <div class="timer-session-list">
            <h3><i class="fas fa-history"></i> Recent Timer Sessions <?php echo isAdmin() ? '(All Users)' : ''; ?></h3>
            <?php if (count($sessions) > 0): ?>
                <?php foreach($sessions as $session): ?>
                <div class="session-item">
                    <div class="session-info">
                        <h4>
                            <?php 
                            $modeText = [
                                'pomodoro' => 'Focus Session',
                                'short_break' => 'Short Break',
                                'long_break' => 'Long Break'
                            ];
                            echo $modeText[$session['mode']] ?? 'Session';
                            ?>
                            <?php if(isAdmin() && isset($session['user_name'])): ?>
                                <span style="font-size: 0.9rem; color: var(--gray); display: block;">
                                    by <?php echo htmlspecialchars($session['user_name']); ?>
                                </span>
                            <?php endif; ?>
                        </h4>
                        <div class="session-time">
                            <?php echo date('M j, g:i A', strtotime($session['completed_at'])); ?>
                            <?php if($session['subject']): ?>
                                • <?php echo htmlspecialchars($session['subject']); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                        <div class="session-duration">
                            <?php echo $session['duration_minutes']; ?> min
                        </div>
                        <?php if(isAdmin()): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="timer_id" value="<?php echo $session['id']; ?>">
                                <button type="submit" name="delete_timer" class="admin-delete-btn" onclick="return confirm('Delete this timer session?')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: var(--gray);">
                    <i class="fas fa-clock" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <h4>No timer sessions yet</h4>
                    <p>Start your first timer session to see it here!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Timer functionality with custom times
        document.addEventListener('DOMContentLoaded', function() {
            const timerDisplay = document.getElementById('timerDisplay');
            const timerMode = document.getElementById('timerMode');
            const startBtn = document.getElementById('startBtn');
            const pauseBtn = document.getElementById('pauseBtn');
            const resetBtn = document.getElementById('resetBtn');
            const modeBtns = document.querySelectorAll('.mode-btn');
            const presetBtns = document.querySelectorAll('.preset-btn');
            const customTimeInput = document.getElementById('customTime');
            const setCustomTimeBtn = document.getElementById('setCustomTime');
            const timerStatus = document.getElementById('timerStatus');
            const timerForm = document.getElementById('timerForm');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            const completedMode = document.getElementById('completedMode');
            const completedDuration = document.getElementById('completedDuration');
            const progressCircle = document.querySelector('.circle-progress-bar');
            
            let timer;
            let timeLeft = <?php echo $custom_focus * 60; ?>;
            let totalTime = <?php echo $custom_focus * 60; ?>;
            let timerRunning = false;
            let currentMode = 'pomodoro';
            let currentDuration = <?php echo $custom_focus; ?>;
            
            // Initialize
            updateDisplay();
            updateProgress();
            updateCircleProgress();
            
            // Mode selection
            modeBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    modeBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    currentDuration = parseInt(this.dataset.minutes);
                    currentMode = this.dataset.mode;
                    totalTime = currentDuration * 60;
                    timeLeft = totalTime;
                    updateDisplay();
                    updateProgress();
                    updateCircleProgress();
                    
                    timerStatus.textContent = getModeText(currentMode) + ' - Ready!';
                    
                    if (timerRunning) {
                        clearInterval(timer);
                        startTimer();
                    }
                });
            });
            
            // Preset buttons
            presetBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    presetBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    
                    const minutes = parseInt(this.dataset.minutes);
                    timeLeft = minutes * 60;
                    totalTime = minutes * 60;
                    currentDuration = minutes;
                    updateDisplay();
                    updateProgress();
                    updateCircleProgress();
                    
                    timerStatus.textContent = `${minutes} min Custom Session - Ready!`;
                    
                    // Activate focus mode with custom time
                    modeBtns.forEach(b => b.classList.remove('active'));
                    modeBtns[0].classList.add('active');
                    currentMode = 'pomodoro';
                    
                    if (timerRunning) {
                        clearInterval(timer);
                        startTimer();
                    }
                });
            });
            
            // Custom time input
            setCustomTimeBtn.addEventListener('click', function() {
                const minutes = parseInt(customTimeInput.value);
                if (minutes < 1 || minutes > 240) {
                    alert('Please enter a time between 1 and 240 minutes');
                    return;
                }
                
                timeLeft = minutes * 60;
                totalTime = minutes * 60;
                currentDuration = minutes;
                updateDisplay();
                updateProgress();
                updateCircleProgress();
                
                timerStatus.textContent = `${minutes} min Custom Session - Ready!`;
                
                // Activate focus mode with custom time
                modeBtns.forEach(b => b.classList.remove('active'));
                modeBtns[0].classList.add('active');
                currentMode = 'pomodoro';
                
                if (timerRunning) {
                    clearInterval(timer);
                    startTimer();
                }
            });
            
            // Start timer
            startBtn.addEventListener('click', startTimer);
            
            function startTimer() {
                if (timerRunning) return;
                
                timerRunning = true;
                startBtn.disabled = true;
                startBtn.innerHTML = '<i class="fas fa-running"></i> Running...';
                pauseBtn.disabled = false;
                timerForm.style.display = 'none';
                
                timer = setInterval(() => {
                    timeLeft--;
                    updateDisplay();
                    updateProgress();
                    updateCircleProgress();
                    
                    if (timeLeft <= 0) {
                        clearInterval(timer);
                        timerRunning = false;
                        startBtn.disabled = false;
                        startBtn.innerHTML = '<i class="fas fa-play"></i> Start Timer';
                        pauseBtn.disabled = true;
                        
                        // Play sound
                        playNotificationSound();
                        
                        // Show completion form
                        completedMode.value = currentMode;
                        completedDuration.value = currentDuration;
                        timerForm.style.display = 'block';
                        timerStatus.textContent = getModeText(currentMode) + ' complete! 🎉';
                        
                        // Show notification
                        if (Notification.permission === 'granted') {
                            new Notification('Timer Complete!', {
                                body: getModeText(currentMode) + ' session finished!'
                            });
                        } else if (Notification.permission !== 'denied') {
                            Notification.requestPermission();
                        }
                    }
                }, 1000);
                
                timerStatus.textContent = getModeText(currentMode) + ' in progress... 🚀';
            }
            
            // Pause timer
            pauseBtn.addEventListener('click', function() {
                if (!timerRunning) return;
                
                clearInterval(timer);
                timerRunning = false;
                startBtn.disabled = false;
                startBtn.innerHTML = '<i class="fas fa-play"></i> Resume';
                pauseBtn.disabled = true;
                timerStatus.textContent = getModeText(currentMode) + ' paused ⏸️';
            });
            
            // Reset timer
            resetBtn.addEventListener('click', resetTimer);
            
            function resetTimer() {
                clearInterval(timer);
                timerRunning = false;
                timeLeft = totalTime;
                updateDisplay();
                updateProgress();
                updateCircleProgress();
                startBtn.disabled = false;
                startBtn.innerHTML = '<i class="fas fa-play"></i> Start Timer';
                pauseBtn.disabled = true;
                timerForm.style.display = 'none';
                timerStatus.textContent = getModeText(currentMode) + ' reset 🔄';
            }
            
            function updateDisplay() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                // Update timer mode text
                timerMode.textContent = getModeText(currentMode) + ` (${Math.floor(totalTime/60)} min)`;
            }
            
            function updateProgress() {
                const progress = ((totalTime - timeLeft) / totalTime) * 100;
                progressBar.style.width = progress + '%';
                progressText.textContent = Math.round(progress) + '%';
            }
            
            function updateCircleProgress() {
                const circumference = 2 * Math.PI * 140;
                const progress = ((totalTime - timeLeft) / totalTime) * circumference;
                progressCircle.style.strokeDashoffset = circumference - progress;
            }
            
            function getModeText(mode) {
                const texts = {
                    'pomodoro': 'Focus Session',
                    'short_break': 'Short Break',
                    'long_break': 'Long Break'
                };
                return texts[mode] || 'Timer';
            }
            
            function playNotificationSound() {
                try {
                    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                    
                    // Play three ascending tones
                    for (let i = 0; i < 3; i++) {
                        setTimeout(() => {
                            const oscillator = audioContext.createOscillator();
                            const gainNode = audioContext.createGain();
                            
                            oscillator.connect(gainNode);
                            gainNode.connect(audioContext.destination);
                            
                            oscillator.frequency.value = 800 + (i * 100);
                            oscillator.type = 'sine';
                            
                            gainNode.gain.setValueAtTime(0.2, audioContext.currentTime);
                            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
                            
                            oscillator.start(audioContext.currentTime);
                            oscillator.stop(audioContext.currentTime + 0.3);
                        }, i * 300);
                    }
                } catch (e) {
                    console.log('Audio not supported');
                }
            }
            
            // Request notification permission
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
            
            // Auto-hide success message
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
            
            // Initialize circle progress
            const circumference = 2 * Math.PI * 140;
            progressCircle.style.strokeDasharray = `${circumference} ${circumference}`;
            progressCircle.style.strokeDashoffset = circumference;
            
            // Add keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.code === 'Space' || e.code === 'Enter') {
                    e.preventDefault();
                    if (timerRunning) {
                        document.getElementById('pauseBtn').click();
                    } else {
                        document.getElementById('startBtn').click();
                    }
                }
                
                if (e.code === 'Escape') {
                    e.preventDefault();
                    document.getElementById('resetBtn').click();
                }
                
                // Number keys for quick time selection
                if (e.code.startsWith('Digit')) {
                    const number = parseInt(e.code.replace('Digit', ''));
                    if (number >= 1 && number <= 9) {
                        const minutes = number * 5; // 5, 10, 15... 45 minutes
                        if (customTimeInput) {
                            customTimeInput.value = minutes;
                            document.getElementById('setCustomTime').click();
                        }
                    }
                }
            });
            
            // Add tooltip for keyboard shortcuts
            startBtn.title = 'Space/Enter to start/pause';
            resetBtn.title = 'Escape to reset';
            
            // Auto-start timer if in demo mode
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('demo') === '1') {
                setTimeout(() => {
                    startTimer();
                }, 1000);
            }
        });
    </script>
</body>
</html>