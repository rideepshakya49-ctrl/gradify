<?php
require_once 'database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');

// Get user stats
$todaySessions = $pdo->prepare("SELECT COUNT(*) FROM study_sessions WHERE user_id = ? AND session_date = ?");
$todaySessions->execute([$user_id, $today]);
$todayCount = $todaySessions->fetchColumn();

$totalSessions = $pdo->prepare("SELECT COUNT(*) FROM study_sessions WHERE user_id = ?");
$totalSessions->execute([$user_id]);
$totalCount = $totalSessions->fetchColumn();

$completedSessions = $pdo->prepare("SELECT COUNT(*) FROM study_sessions WHERE user_id = ? AND status = 'completed'");
$completedSessions->execute([$user_id]);
$completedCount = $completedSessions->fetchColumn();

// Get timer stats
$todayTimer = $pdo->prepare("SELECT SUM(duration_minutes) as total FROM timer_sessions WHERE user_id = ? AND DATE(completed_at) = ?");
$todayTimer->execute([$user_id, $today]);
$timerStats = $todayTimer->fetch(PDO::FETCH_ASSOC);
$timerMinutes = $timerStats['total'] ?? 0;

// Upcoming sessions (next 7 days)
$upcoming = $pdo->prepare("SELECT * FROM study_sessions WHERE user_id = ? AND session_date >= ? AND status = 'pending' ORDER BY session_date, start_time LIMIT 5");
$upcoming->execute([$user_id, $today]);
$upcomingPlans = $upcoming->fetchAll(PDO::FETCH_ASSOC);

// Recent completed sessions
$recent = $pdo->prepare("SELECT * FROM study_sessions WHERE user_id = ? AND status = 'completed' ORDER BY session_date DESC LIMIT 5");
$recent->execute([$user_id]);
$recentPlans = $recent->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Gradify</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem;
            border-radius: var(--radius);
            margin: 2rem 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 100" preserveAspectRatio="none"><path d="M0,0 L1000,0 L1000,100 L0,100 Z" fill="white" opacity="0.1"/></svg>');
            background-size: 100% 100%;
        }
        
        .dashboard-hero h1 {
            color: white;
            margin-bottom: 0.5rem;
            font-size: 2.5rem;
        }
        
        .dashboard-hero p {
            opacity: 0.9;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            line-height: 1;
            margin: 0.5rem 0;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .quick-actions {
            background: white;
            padding: 2.5rem;
            border-radius: var(--radius);
            margin: 2rem 0;
            box-shadow: var(--shadow);
        }
        
        .quick-actions h2 {
            margin-bottom: 1.5rem;
            color: var(--dark);
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .action-card {
            flex: 1;
            min-width: 200px;
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: var(--radius-sm);
            border-left: 4px solid #667eea;
            transition: var(--transition);
        }
        
        .action-card:hover {
            background: #f1f5f9;
            transform: translateX(5px);
        }
        
        .action-card h3 {
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }
        
        .action-card p {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .section {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            margin: 2rem 0;
            box-shadow: var(--shadow);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .section-header h2 {
            margin: 0;
        }
        
        .no-data {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }
        
        .no-data i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .progress-ring {
            width: 100px;
            height: 100px;
            margin: 0 auto 1rem;
            position: relative;
        }
        
        .progress-ring-circle {
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        
        .progress-ring-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.5rem;
            font-weight: 700;
            color: #667eea;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <!-- Dashboard Hero -->
        <div class="dashboard-hero">
            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>! 👋</h1>
            <p>Track your progress and optimize your study sessions for maximum productivity</p>
            
            <div style="display: flex; justify-content: center; gap: 1rem; margin-top: 2rem;">
                <span class="plan-stat" style="background: rgba(255,255,255,0.2);">
                    <strong><?php echo $todayCount; ?></strong> plans today
                </span>
                <span class="plan-stat" style="background: rgba(255,255,255,0.2);">
                    <strong><?php echo $timerMinutes; ?></strong> minutes focused
                </span>
                <span class="plan-stat" style="background: rgba(255,255,255,0.2);">
                    <strong><?php echo $completedCount; ?></strong> completed
                </span>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-calendar-day stat-icon"></i>
                <p class="stat-number"><?php echo $todayCount; ?></p>
                <p class="stat-label">Today's Plans</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-tasks stat-icon"></i>
                <p class="stat-number"><?php echo $totalCount; ?></p>
                <p class="stat-label">Total Plans</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-check-circle stat-icon"></i>
                <p class="stat-number"><?php echo $completedCount; ?></p>
                <p class="stat-label">Completed</p>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-chart-line stat-icon"></i>
                <?php if($totalCount > 0): ?>
                    <div class="progress-ring">
                        <svg width="100" height="100">
                            <circle class="progress-ring-circle" 
                                   stroke="#e2e8f0" 
                                   stroke-width="8" 
                                   fill="transparent" 
                                   r="40" 
                                   cx="50" 
                                   cy="50" />
                            <circle class="progress-ring-circle" 
                                   stroke="#667eea" 
                                   stroke-width="8" 
                                   fill="transparent" 
                                   r="40" 
                                   cx="50" 
                                   cy="50"
                                   stroke-dasharray="<?php echo 2 * pi() * 40; ?>"
                                   stroke-dashoffset="<?php echo 2 * pi() * 40 * (1 - ($completedCount/$totalCount)); ?>" />
                        </svg>
                        <div class="progress-ring-text">
                            <?php echo round(($completedCount/$totalCount)*100); ?>%
                        </div>
                    </div>
                <?php else: ?>
                    <p class="stat-number">0%</p>
                <?php endif; ?>
                <p class="stat-label">Progress Rate</p>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2>Quick Actions</h2>
            <div class="action-buttons">
                <div class="action-card">
                    <h3><i class="fas fa-plus-circle" style="color: #667eea;"></i> Create Plan</h3>
                    <p>Schedule a new study session with specific goals</p>
                    <a href="plans.php?action=add" class="btn btn-small btn-primary">
                        <i class="fas fa-plus"></i> Add Plan
                    </a>
                </div>
                
                <div class="action-card">
                    <h3><i class="fas fa-stopwatch" style="color: #48bb78;"></i> Start Timer</h3>
                    <p>Focus with our Pomodoro timer and track your sessions</p>
                    <a href="timer.php" class="btn btn-small btn-success">
                        <i class="fas fa-play"></i> Start Timer
                    </a>
                </div>
                
                <div class="action-card">
                    <h3><i class="fas fa-calendar-week" style="color: #ed8936;"></i> View Routines</h3>
                    <p>Manage your daily study routines and habits</p>
                    <a href="routines.php" class="btn btn-small" style="background: #ed8936; color: white;">
                        <i class="fas fa-tasks"></i> View Routines
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Two Column Layout -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 2rem; margin: 2rem 0;">
            <!-- Upcoming Plans -->
            <div class="section">
                <div class="section-header">
                    <h2>Upcoming Study Plans</h2>
                    <a href="plans.php" class="btn btn-small">View All</a>
                </div>
                
                <?php if(count($upcomingPlans) > 0): ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach($upcomingPlans as $plan): ?>
                        <div class="plan-card" style="margin-bottom: 1rem; padding: 1rem;">
                            <div class="plan-header" style="margin-bottom: 0.5rem;">
                                <h3 style="font-size: 1rem; margin: 0;"><?php echo htmlspecialchars($plan['title']); ?></h3>
                                <span class="status-badge status-pending" style="font-size: 0.75rem;">
                                    Pending
                                </span>
                            </div>
                            <div class="plan-time" style="font-size: 0.85rem;">
                                <i class="far fa-calendar"></i> 
                                <?php echo date('M d', strtotime($plan['session_date'])); ?>
                                • 
                                <i class="far fa-clock"></i>
                                <?php echo date('g:i A', strtotime($plan['start_time'])); ?>
                            </div>
                            <div style="margin-top: 0.5rem;">
                                <a href="plans.php?action=edit&id=<?php echo $plan['id']; ?>" class="btn-small" style="padding: 0.25rem 0.75rem; font-size: 0.85rem;">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="plans.php?action=complete&id=<?php echo $plan['id']; ?>" 
                                   class="btn-small btn-success" 
                                   style="padding: 0.25rem 0.75rem; font-size: 0.85rem;"
                                   onclick="return confirm('Mark as complete?')">
                                    <i class="fas fa-check"></i> Complete
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-calendar-plus"></i>
                        <h3>No upcoming plans</h3>
                        <p>Schedule your next study session</p>
                        <a href="plans.php?action=add" class="btn btn-primary" style="margin-top: 1rem;">
                            <i class="fas fa-plus"></i> Create Plan
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Activity -->
            <div class="section">
                <div class="section-header">
                    <h2>Recent Activity</h2>
                    <a href="plans.php?filter=completed" class="btn btn-small">View All</a>
                </div>
                
                <?php if(count($recentPlans) > 0): ?>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php foreach($recentPlans as $plan): ?>
                        <div class="plan-card completed" style="margin-bottom: 1rem; padding: 1rem;">
                            <div class="plan-header" style="margin-bottom: 0.5rem;">
                                <h3 style="font-size: 1rem; margin: 0;"><?php echo htmlspecialchars($plan['title']); ?></h3>
                                <span class="status-badge status-completed" style="font-size: 0.75rem;">
                                    Completed
                                </span>
                            </div>
                            <div class="plan-time" style="font-size: 0.85rem;">
                                <i class="far fa-calendar"></i> 
                                <?php echo date('M d', strtotime($plan['session_date'])); ?>
                                • 
                                <i class="far fa-clock"></i>
                                <?php echo date('g:i A', strtotime($plan['start_time'])); ?>
                            </div>
                            <?php if(!empty($plan['description'])): ?>
                                <div class="plan-description" style="font-size: 0.9rem; margin: 0.5rem 0;">
                                    <?php echo substr(htmlspecialchars($plan['description']), 0, 100); ?>...
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-history"></i>
                        <h3>No recent activity</h3>
                        <p>Complete your first study session to see activity here</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Study Tips -->
        <div class="section" style="background: linear-gradient(135deg, #f6f9fc 0%, #edf2f7 100%);">
            <h2 style="text-align: center; margin-bottom: 2rem;">Study Tips</h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                <div style="background: white; padding: 1.5rem; border-radius: var(--radius-sm); box-shadow: var(--shadow);">
                    <i class="fas fa-brain" style="font-size: 2rem; color: #667eea; margin-bottom: 1rem;"></i>
                    <h3>Pomodoro Technique</h3>
                    <p style="font-size: 0.9rem; color: var(--gray);">Use 25-minute focused sessions with 5-minute breaks to maintain concentration.</p>
                </div>
                <div style="background: white; padding: 1.5rem; border-radius: var(--radius-sm); box-shadow: var(--shadow);">
                    <i class="fas fa-book" style="font-size: 2rem; color: #48bb78; margin-bottom: 1rem;"></i>
                    <h3>Spaced Repetition</h3>
                    <p style="font-size: 0.9rem; color: var(--gray);">Review material at increasing intervals to improve long-term retention.</p>
                </div>
                <div style="background: white; padding: 1.5rem; border-radius: var(--radius-sm); box-shadow: var(--shadow);">
                    <i class="fas fa-chart-pie" style="font-size: 2rem; color: #ed8936; margin-bottom: 1rem;"></i>
                    <h3>Active Recall</h3>
                    <p style="font-size: 0.9rem; color: var(--gray);">Test yourself regularly instead of just re-reading notes.</p>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Gradify. Making studying smarter, not harder.</p>
        </div>
    </footer>
    
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
        
        // Animate progress rings
        document.querySelectorAll('.progress-ring-circle').forEach(circle => {
            const radius = circle.r.baseVal.value;
            const circumference = 2 * Math.PI * radius;
            circle.style.strokeDasharray = `${circumference} ${circumference}`;
        });
    </script>
</body>
</html>