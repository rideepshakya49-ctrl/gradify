<?php
require_once 'database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$message = '';

// Handle actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    // Add/Edit Plan
    if ($action == 'add' || $action == 'edit') {
        $plan = ['id' => 0, 'title' => '', 'subject' => '', 'description' => '', 
                'session_date' => date('Y-m-d'), 'start_time' => '09:00', 
                'end_time' => '10:00', 'status' => 'pending', 'user_id' => $user_id];
        
        if ($action == 'edit' && isset($_GET['id'])) {
            // Admin can edit any plan, users can only edit their own
            if (isAdmin()) {
                $stmt = $pdo->prepare("SELECT * FROM study_sessions WHERE id = ?");
                $stmt->execute([$_GET['id']]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM study_sessions WHERE id = ? AND user_id = ?");
                $stmt->execute([$_GET['id'], $user_id]);
            }
            $plan = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$plan) {
                redirect('plans.php');
            }
        }
        
        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $title = trim($_POST['title']);
            $subject = $_POST['subject'];
            $description = trim($_POST['description']);
            $session_date = $_POST['session_date'];
            $start_time = $_POST['start_time'];
            $end_time = $_POST['end_time'];
            $status = $_POST['status'];
            
            // Get current date and time for validation
            $current_date = date('Y-m-d');
            $current_time = date('H:i');
            $selected_datetime = $session_date . ' ' . $start_time;
            $current_datetime = $current_date . ' ' . $current_time;
            
            if (empty($title) || empty($subject)) {
                $message = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> Title and subject are required</div>';
            } elseif ($start_time >= $end_time) {
                $message = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> End time must be after start time</div>';
            } else {
                // Only validate past dates for non-admin users when adding new plans
                if ($action == 'add' && !isAdmin() && $selected_datetime < $current_datetime) {
                    $message = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> Cannot schedule plans in the past. Please select a future date/time.</div>';
                } else {
                    if ($action == 'add') {
                        $stmt = $pdo->prepare("INSERT INTO study_sessions 
                            (user_id, title, subject, description, session_date, start_time, end_time, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$user_id, $title, $subject, $description, $session_date, 
                                      $start_time, $end_time, $status]);
                        $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Study plan added successfully!</div>';
                    } else {
                        // For editing, only check past dates if changing date/time
                        $is_past_plan = ($plan['session_date'] < $current_date) || 
                                       ($plan['session_date'] == $current_date && $plan['start_time'] < $current_time);
                        
                        // Allow admin to edit past plans, but regular users need validation
                        if (isAdmin() || !$is_past_plan) {
                            // Admin can edit any plan, users can only edit their own
                            if (isAdmin()) {
                                $stmt = $pdo->prepare("UPDATE study_sessions SET 
                                    title = ?, subject = ?, description = ?, session_date = ?, 
                                    start_time = ?, end_time = ?, status = ? 
                                    WHERE id = ?");
                                $stmt->execute([$title, $subject, $description, $session_date, 
                                              $start_time, $end_time, $status, $_GET['id']]);
                            } else {
                                $stmt = $pdo->prepare("UPDATE study_sessions SET 
                                    title = ?, subject = ?, description = ?, session_date = ?, 
                                    start_time = ?, end_time = ?, status = ? 
                                    WHERE id = ? AND user_id = ?");
                                $stmt->execute([$title, $subject, $description, $session_date, 
                                              $start_time, $end_time, $status, $_GET['id'], $user_id]);
                            }
                            $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Study plan updated successfully!</div>';
                        } else {
                            $message = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> Cannot modify past plans. Please create a new plan.</div>';
                        }
                    }
                    
                    if (strpos($message, 'success') !== false) {
                        header("refresh:2;url=plans.php");
                    }
                }
            }
        }
        
        // Show form
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title><?php echo $action == 'add' ? 'Add Study Plan' : 'Edit Study Plan'; ?> - Gradify</title>
            <link rel="stylesheet" href="style.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                /* .time-note style removed */
                
                .past-plan-warning {
                    background: #fff5f5;
                    border-left: 4px solid #f56565;
                    padding: 10px 15px;
                    margin: 15px 0;
                    border-radius: 4px;
                    font-size: 0.9rem;
                }
            </style>
        </head>
        <body>
            <?php include 'navbar.php'; ?>
            
            <div class="container">
                <div class="plans-header" style="background: white; color: var(--dark);">
                    <h1><?php echo $action == 'add' ? 'Add New Study Plan' : 'Edit Study Plan'; ?></h1>
                    <p><?php echo $action == 'add' ? 'Schedule a new study session' : 'Update your study plan'; ?></p>
                </div>
                
                <?php echo $message; ?>
                
                <!-- Show warning for past plans being edited -->
                <?php 
                $current_date = date('Y-m-d');
                $current_time = date('H:i');
                $is_past_plan = false;
                if ($action == 'edit' && isset($plan['session_date'])) {
                    $is_past_plan = ($plan['session_date'] < $current_date) || 
                                   ($plan['session_date'] == $current_date && $plan['start_time'] < $current_time);
                    
                    if ($is_past_plan && !isAdmin()): ?>
                        <div class="past-plan-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            This is a past plan. You can only update the status or description.
                            Date and time cannot be changed for past plans.
                        </div>
                    <?php endif;
                }
                
                if ($action == 'add' && !isAdmin()): ?>
                    <!-- Note removed: You can only schedule plans for future dates and times. -->
                <?php endif; ?>
                
                <form method="POST" class="form-container" id="planForm">
                    <div class="form-group">
                        <label><i class="fas fa-heading"></i> Plan Title *</label>
                        <input type="text" name="title" value="<?php echo htmlspecialchars($plan['title']); ?>" required 
                               placeholder="e.g., Calculus Chapter 3 Review">
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-book"></i> Subject *</label>
                            <select name="subject" required>
                                <option value="">Select Subject</option>
                                <option value="Mathematics" <?php echo $plan['subject'] == 'Mathematics' ? 'selected' : ''; ?>>Mathematics</option>
                                <option value="Science" <?php echo $plan['subject'] == 'Science' ? 'selected' : ''; ?>>Science</option>
                                <option value="English" <?php echo $plan['subject'] == 'English' ? 'selected' : ''; ?>>English</option>
                                <option value="History" <?php echo $plan['subject'] == 'History' ? 'selected' : ''; ?>>History</option>
                                <option value="Computer Science" <?php echo $plan['subject'] == 'Computer Science' ? 'selected' : ''; ?>>Computer Science</option>
                                <option value="Physics" <?php echo $plan['subject'] == 'Physics' ? 'selected' : ''; ?>>Physics</option>
                                <option value="Chemistry" <?php echo $plan['subject'] == 'Chemistry' ? 'selected' : ''; ?>>Chemistry</option>
                                <option value="Biology" <?php echo $plan['subject'] == 'Biology' ? 'selected' : ''; ?>>Biology</option>
                                <option value="Other" <?php echo $plan['subject'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-tag"></i> Status</label>
                            <select name="status">
                                <option value="pending" <?php echo $plan['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="completed" <?php echo $plan['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $plan['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-align-left"></i> Description (Optional)</label>
                        <textarea name="description" rows="4" placeholder="What will you focus on during this study session? Specific topics, exercises, or goals..."><?php echo htmlspecialchars($plan['description']); ?></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label><i class="fas fa-calendar"></i> Date *</label>
                            <input type="date" name="session_date" id="session_date" 
                                   value="<?php echo $plan['session_date']; ?>" required 
                                   min="<?php echo date('Y-m-d'); ?>"
                                   <?php echo ($is_past_plan && !isAdmin()) ? 'readonly' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> Start Time *</label>
                            <input type="time" name="start_time" id="start_time" 
                                   value="<?php echo $plan['start_time']; ?>" required
                                   <?php echo ($is_past_plan && !isAdmin()) ? 'readonly' : ''; ?>>
                        </div>
                        
                        <div class="form-group">
                            <label><i class="fas fa-clock"></i> End Time *</label>
                            <input type="time" name="end_time" id="end_time" 
                                   value="<?php echo $plan['end_time']; ?>" required
                                   <?php echo ($is_past_plan && !isAdmin()) ? 'readonly' : ''; ?>>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Create Plan' : 'Update Plan'; ?>
                        </button>
                        <a href="plans.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
            
            <script>
                // Real-time validation for date and time
                document.addEventListener('DOMContentLoaded', function() {
                    const sessionDate = document.getElementById('session_date');
                    const startTime = document.getElementById('start_time');
                    const endTime = document.getElementById('end_time');
                    const form = document.getElementById('planForm');
                    
                    // Set minimum time based on selected date
                    sessionDate.addEventListener('change', function() {
                        const today = new Date().toISOString().split('T')[0];
                        const selectedDate = this.value;
                        
                        if (selectedDate === today) {
                            // If selecting today, set minimum time to current time + 5 minutes
                            const now = new Date();
                            now.setMinutes(now.getMinutes() + 5); // Allow scheduling 5 minutes from now
                            const minTime = now.toTimeString().slice(0, 5);
                            startTime.min = minTime;
                            endTime.min = minTime;
                        } else if (selectedDate > today) {
                            // If selecting future date, remove time restrictions
                            startTime.removeAttribute('min');
                            endTime.removeAttribute('min');
                        }
                    });
                    
                    // Set initial minimum time if date is today
                    const today = new Date().toISOString().split('T')[0];
                    if (sessionDate.value === today) {
                        const now = new Date();
                        now.setMinutes(now.getMinutes() + 5);
                        const minTime = now.toTimeString().slice(0, 5);
                        startTime.min = minTime;
                        endTime.min = minTime;
                    }
                    
                    // Validate end time is after start time
                    startTime.addEventListener('change', validateTime);
                    endTime.addEventListener('change', validateTime);
                    
                    function validateTime() {
                        if (startTime.value && endTime.value) {
                            if (startTime.value >= endTime.value) {
                                endTime.setCustomValidity('End time must be after start time');
                                endTime.style.borderColor = '#f56565';
                            } else {
                                endTime.setCustomValidity('');
                                endTime.style.borderColor = '';
                            }
                        }
                    }
                    
                    // Form validation
                    form.addEventListener('submit', function(e) {
                        const today = new Date().toISOString().split('T')[0];
                        const currentTime = new Date().toTimeString().slice(0, 5);
                        const selectedDate = sessionDate.value;
                        const selectedTime = startTime.value;
                        
                        // Check if user is admin (admins can schedule past dates)
                        const isAdmin = <?php echo isAdmin() ? 'true' : 'false'; ?>;
                        
                        if (!isAdmin) {
                            if (selectedDate < today) {
                                e.preventDefault();
                                alert('Cannot schedule plans in the past. Please select today or a future date.');
                                return false;
                            }
                            
                            if (selectedDate === today && selectedTime < currentTime) {
                                e.preventDefault();
                                alert('Cannot schedule plans in the past. Please select a time in the future.');
                                return false;
                            }
                        }
                        
                        return true;
                    });
                    
                    // Initialize validation
                    validateTime();
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    
    // Delete plan - admin can delete any, users only their own
    if ($action == 'delete' && isset($_GET['id'])) {
        if (isAdmin()) {
            $stmt = $pdo->prepare("DELETE FROM study_sessions WHERE id = ?");
            $stmt->execute([$_GET['id']]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM study_sessions WHERE id = ? AND user_id = ?");
            $stmt->execute([$_GET['id'], $user_id]);
        }
        redirect('plans.php?msg=deleted');
    }
    
    // Mark as complete - admin can complete any, users only their own
    if ($action == 'complete' && isset($_GET['id'])) {
        if (isAdmin()) {
            $stmt = $pdo->prepare("UPDATE study_sessions SET status = 'completed' WHERE id = ?");
            $stmt->execute([$_GET['id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE study_sessions SET status = 'completed' WHERE id = ? AND user_id = ?");
            $stmt->execute([$_GET['id'], $user_id]);
        }
        redirect('plans.php?msg=completed');
    }
}

// List all plans - admin sees all, users see only their own
if (isAdmin()) {
    $stmt = $pdo->prepare("SELECT ss.*, u.username as user_name FROM study_sessions ss 
                          LEFT JOIN users u ON ss.user_id = u.id 
                          ORDER BY ss.session_date DESC, ss.start_time DESC");
    $stmt->execute();
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT * FROM study_sessions WHERE user_id = ? ORDER BY session_date DESC, start_time DESC");
    $stmt->execute([$user_id]);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate statistics
$totalPlans = count($plans);
$completedPlans = count(array_filter($plans, fn($p) => $p['status'] == 'completed'));
$pendingPlans = count(array_filter($plans, fn($p) => $p['status'] == 'pending'));
$todayPlans = count(array_filter($plans, fn($p) => $p['session_date'] == date('Y-m-d')));

// Show message
if (isset($_GET['msg'])) {
    switch ($_GET['msg']) {
        case 'deleted': 
            $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Study plan deleted successfully!</div>'; 
            break;
        case 'completed': 
            $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Study plan marked as completed!</div>'; 
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Study Plans - Gradify</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <!-- Header -->
        <div class="plans-header">
            <h1><i class="fas fa-calendar-alt"></i> My Study Plans</h1>
            <p>Organize and track your study sessions effectively</p>
            
            <!-- Stats -->
            <div class="plans-stats">
                <div class="plan-stat">
                    <strong><?php echo $totalPlans; ?></strong> Total Plans
                </div>
                <div class="plan-stat">
                    <strong><?php echo $completedPlans; ?></strong> Completed
                </div>
                <div class="plan-stat">
                    <strong><?php echo $pendingPlans; ?></strong> Pending
                </div>
                <div class="plan-stat">
                    <strong><?php echo $todayPlans; ?></strong> Today
                </div>
            </div>
            
            <a href="plans.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus"></i> Create New Plan
            </a>
        </div>
        
        <?php echo $message; ?>
        
        <!-- Filter Buttons -->
        <div class="filter-buttons">
            <button class="filter-btn active" onclick="filterPlans('all')">All Plans</button>
            <button class="filter-btn" onclick="filterPlans('pending')">Pending</button>
            <button class="filter-btn" onclick="filterPlans('completed')">Completed</button>
            <button class="filter-btn" onclick="filterPlans('today')">Today</button>
            <button class="filter-btn" onclick="filterPlans('upcoming')">Upcoming</button>
        </div>
        
        <!-- Plans List -->
        <div id="plansList">
            <?php if(count($plans) > 0): ?>
                <?php 
                $current_date = date('Y-m-d');
                $current_time = date('H:i');
                foreach($plans as $plan): 
                    $is_past_plan = ($plan['session_date'] < $current_date) || 
                                   ($plan['session_date'] == $current_date && $plan['start_time'] < $current_time);
                ?>
                <div class="plan-card <?php echo $plan['status']; ?>" 
                     data-status="<?php echo $plan['status']; ?>"
                     data-date="<?php echo $plan['session_date']; ?>">
                    
                    <div class="plan-header">
                        <div>
                            <h3><?php echo htmlspecialchars($plan['title']); ?></h3>
                            <span class="plan-subject"><?php echo htmlspecialchars($plan['subject']); ?></span>
                            <?php if($is_past_plan && $plan['status'] == 'pending'): ?>
                                <span style="font-size: 0.8rem; color: var(--warning); display: block; margin-top: 5px;">
                                    <i class="fas fa-clock"></i> Missed
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <span class="status-badge status-<?php echo $plan['status']; ?>">
                                <?php echo ucfirst($plan['status']); ?>
                            </span>
                            <?php if(isAdmin() && isset($plan['user_name'])): ?>
                                <span style="font-size: 0.8rem; color: var(--gray); display: block; margin-top: 5px;">
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($plan['user_name']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="plan-time">
                        <i class="far fa-calendar"></i> 
                        <?php echo date('l, F j, Y', strtotime($plan['session_date'])); ?>
                        • 
                        <i class="far fa-clock"></i>
                        <?php echo date('g:i A', strtotime($plan['start_time'])); ?> - 
                        <?php echo date('g:i A', strtotime($plan['end_time'])); ?>
                        <?php if($is_past_plan): ?>
                            <span style="color: var(--warning); margin-left: 10px;">
                                <i class="fas fa-history"></i> Past
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if(!empty($plan['description'])): ?>
                        <div class="plan-description">
                            <?php echo nl2br(htmlspecialchars($plan['description'])); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="actions" style="display: flex; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--light-gray);">
                        <?php if($plan['status'] == 'pending' || isAdmin()): ?>
                            <a href="plans.php?action=complete&id=<?php echo $plan['id']; ?>" 
                               class="btn btn-small btn-success" 
                               onclick="return confirm('Mark this plan as completed?')">
                                <i class="fas fa-check"></i> Complete
                            </a>
                        <?php endif; ?>
                        
                        <?php if(isAdmin() || (!isAdmin() && $plan['user_id'] == $user_id)): ?>
                            <a href="plans.php?action=edit&id=<?php echo $plan['id']; ?>" class="btn btn-small">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            
                            <a href="plans.php?action=delete&id=<?php echo $plan['id']; ?>" 
                               class="btn btn-small btn-danger" 
                               onclick="return confirm('Delete this study plan? This action cannot be undone.')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        <?php endif; ?>
                        
                        <?php if(isAdmin()): ?>
                            <span style="font-size: 0.8rem; color: var(--gray); margin-left: auto;">
                                User ID: <?php echo $plan['user_id']; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="section" style="text-align: center; padding: 3rem;">
                    <i class="fas fa-calendar-plus" style="font-size: 3rem; color: var(--gray); margin-bottom: 1rem;"></i>
                    <h3>No study plans yet</h3>
                    <p>Start organizing your study sessions by creating your first plan!</p>
                    <a href="plans.php?action=add" class="btn btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-plus"></i> Create First Plan
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Filter plans
        function filterPlans(filter) {
            const buttons = document.querySelectorAll('.filter-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            const plans = document.querySelectorAll('.plan-card');
            const today = new Date().toISOString().split('T')[0];
            
            plans.forEach(plan => {
                const status = plan.dataset.status;
                const date = plan.dataset.date;
                
                let show = false;
                
                switch(filter) {
                    case 'all':
                        show = true;
                        break;
                    case 'pending':
                        show = status === 'pending';
                        break;
                    case 'completed':
                        show = status === 'completed';
                        break;
                    case 'today':
                        show = date === today;
                        break;
                    case 'upcoming':
                        show = date >= today && status === 'pending';
                        break;
                }
                
                plan.style.display = show ? 'block' : 'none';
            });
        }
        
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