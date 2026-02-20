<?php
require_once 'database.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$message = '';
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['delete_id'])) {
        // Delete routine - admin can delete any, users only their own
        if (isAdmin()) {
            $stmt = $pdo->prepare("DELETE FROM routine_activities WHERE id = ?");
            $stmt->execute([$_POST['delete_id']]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM routine_activities WHERE id = ? AND user_id = ?");
            $stmt->execute([$_POST['delete_id'], $user_id]);
        }
        $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Activity deleted!</div>';
    } else {
        // Add routine
        $day_of_week = $_POST['day_of_week'];
        $activity = trim($_POST['activity']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        
        // Validate time - can't add past time for today if it's that day
        $current_day = date('l'); // Get current day of week
        $current_time = date('H:i');
        
        if (empty($activity) || $start_time >= $end_time) {
            $message = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> End time must be after start time</div>';
        } else {
            // Check for time conflicts on the same day
            if (isAdmin()) {
                $conflictStmt = $pdo->prepare("SELECT * FROM routine_activities 
                                              WHERE day_of_week = ? 
                                              AND start_time < ? AND end_time > ?");
                $conflictStmt->execute([$day_of_week, $end_time, $start_time]);
            } else {
                $conflictStmt = $pdo->prepare("SELECT * FROM routine_activities 
                                              WHERE user_id = ? AND day_of_week = ? 
                                              AND start_time < ? AND end_time > ?");
                $conflictStmt->execute([$user_id, $day_of_week, $end_time, $start_time]);
            }
            $conflicts = $conflictStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($conflicts) > 0) {
                // Find the conflicting activity
                $conflict = $conflicts[0];
                $conflict_start = date('g:i A', strtotime($conflict['start_time']));
                $conflict_end = date('g:i A', strtotime($conflict['end_time']));
                $message = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> Time conflict! This overlaps with "' . htmlspecialchars($conflict['activity']) . '" (' . $conflict_start . ' - ' . $conflict_end . ')</div>';
            } else {
                // Only validate for non-admin users
                if (!isAdmin() && $day_of_week == $current_day && $start_time < $current_time) {
                    $message = '<div class="alert error"><i class="fas fa-exclamation-circle"></i> Cannot schedule routine activities in the past. Please select a future time.</div>';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO routine_activities (user_id, day_of_week, activity, start_time, end_time) 
                                          VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$user_id, $day_of_week, $activity, $start_time, $end_time]);
                    $message = '<div class="alert success"><i class="fas fa-check-circle"></i> Activity added to routine!</div>';
                }
            }
        }
    }
}

// Get all routines - admin sees all, users see only their own
$routines = [];
$allActivities = []; // Store all activities for time checking
foreach ($days as $day) {
    if (isAdmin()) {
        $stmt = $pdo->prepare("SELECT ra.*, u.username as user_name FROM routine_activities ra 
                              LEFT JOIN users u ON ra.user_id = u.id 
                              WHERE day_of_week = ? 
                              ORDER BY start_time");
        $stmt->execute([$day]);
        $routines[$day] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM routine_activities 
                              WHERE user_id = ? AND day_of_week = ? 
                              ORDER BY start_time");
        $stmt->execute([$user_id, $day]);
        $routines[$day] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Store for JavaScript time conflict checking
    $allActivities[$day] = $routines[$day];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Daily Routines - Gradify</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .time-note {
            background: #e8f4fd;
            border-left: 4px solid #4299e1;
            padding: 10px 15px;
            margin: 15px 0;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .time-conflict-note {
            background: #fff5f5;
            border-left: 4px solid #f56565;
            padding: 10px 15px;
            margin: 15px 0;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .past-activity {
            opacity: 0.7;
            background: #f7fafc;
        }
        
        .past-time {
            color: #a0aec0;
            font-style: italic;
        }
        
        .conflict-warning {
            background: #fff5f5;
            border: 2px dashed #f56565;
            animation: pulseWarning 2s infinite;
        }
        
        @keyframes pulseWarning {
            0% { border-color: #f56565; }
            50% { border-color: #fed7d7; }
            100% { border-color: #f56565; }
        }
        
        /* Non-overlapping grid layout */
        .days-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .day-card {
            background: white;
            border: 1px solid var(--light-gray);
            border-radius: var(--radius);
            padding: 1.5rem;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            min-height: 200px;
        }
        
        .day-card:hover {
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }
        
        .day-card.today-card {
            border: 2px solid var(--primary);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }
        
        .day-card h4 {
            color: var(--primary);
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--light-gray);
            font-size: 1.2rem;
        }
        
        .day-activities {
            flex: 1;
            min-height: 100px;
            overflow-y: auto;
            max-height: 300px;
            padding-right: 5px;
        }
        
        /* Timeline visualization */
        .timeline-container {
            margin: 1rem 0;
            background: #f8fafc;
            border-radius: 8px;
            padding: 0.5rem;
            position: relative;
            height: 40px;
        }
        
        .timeline {
            position: relative;
            height: 20px;
            background: #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .time-slot {
            position: absolute;
            height: 100%;
            background: var(--primary);
            border-radius: 10px;
            opacity: 0.7;
            transition: all 0.3s;
        }
        
        .time-slot:hover {
            opacity: 1;
            transform: scaleY(1.1);
        }
        
        .time-labels {
            display: flex;
            justify-content: space-between;
            font-size: 0.7rem;
            color: var(--gray);
            margin-top: 0.25rem;
        }
        
        .activity-item {
            background: #f8fafc;
            padding: 1rem;
            border-radius: var(--radius-sm);
            margin-bottom: 0.75rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: var(--primary);
        }
        
        .activity-item.conflict {
            background: #fff5f5;
            border: 1px solid #fed7d7;
        }
        
        .activity-item.conflict::before {
            background: var(--danger);
        }
        
        .activity-item:hover {
            background: #edf2f7;
        }
        
        .activity-info {
            flex: 1;
            min-width: 0;
            margin-right: 10px;
        }
        
        .activity-info strong {
            display: block;
            color: var(--dark);
            margin-bottom: 0.25rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .activity-time {
            display: block;
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 0.25rem;
        }
        
        .delete-form {
            margin: 0;
            flex-shrink: 0;
        }
        
        .btn-delete {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 1rem;
            padding: 0.25rem;
            border-radius: 50%;
            transition: var(--transition);
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-delete:hover {
            background: var(--danger);
            color: white;
        }
        
        .no-activities {
            text-align: center;
            color: var(--gray);
            font-style: italic;
            padding: 2rem 0;
        }
        
        .routine-container {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin: 2rem 0;
        }
        
        .add-routine-form {
            background: #f8fafc;
            padding: 2rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            border: 1px solid var(--light-gray);
        }
        
        .weekly-schedule {
            margin-top: 2rem;
        }
        
        /* Time conflict visualization */
        .conflict-marker {
            position: absolute;
            background: rgba(245, 101, 101, 0.3);
            border: 2px dashed #f56565;
            z-index: 1;
            pointer-events: none;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .days-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .routine-container {
                padding: 1rem;
            }
            
            .add-routine-form {
                padding: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .days-grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                flex-direction: column;
            }
            
            .form-row .form-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container">
        <div class="plans-header" style="text-align: center;">
            <h1><i class="fas fa-tasks"></i> Daily Study Routines</h1>
            <p>Organize your weekly study schedule and build consistent habits</p>
        </div>
        
        <?php echo $message; ?>
        
        <div class="routine-container">
            <!-- Add Activity Form -->
            <?php if(!isAdmin()): ?>
                <div class="add-routine-form">
                    <h3><i class="fas fa-plus-circle"></i> Add New Activity</h3>
                    
                    <?php 
                    $current_day = date('l');
                    $current_time = date('H:i');
                    ?>
                    
                    
                    
                    <div class="time-conflict-note" id="conflictNote" style="display: none;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong>Time Conflict:</strong> <span id="conflictMessage"></span>
                    </div>
                    
                    <form method="POST" id="routineForm">
                        <div class="form-row">
                            <div class="form-group">
                                <label><i class="fas fa-calendar-day"></i> Day of Week</label>
                                <select name="day_of_week" id="day_of_week" required>
                                    <?php foreach($days as $day): ?>
                                        <option value="<?php echo $day; ?>" <?php echo $day == $current_day ? 'selected' : ''; ?>>
                                            <?php echo $day; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-tasks"></i> Activity</label>
                                <input type="text" name="activity" id="activity" placeholder="e.g., Math Practice, Physics Study, English Reading" required>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-clock"></i> Start Time</label>
                                    <input type="time" name="start_time" id="start_time" value="09:00" required>
                                </div>
                                
                                <div class="form-group">
                                    <label><i class="fas fa-clock"></i> End Time</label>
                                    <input type="time" name="end_time" id="end_time" value="10:00" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Time Conflict Visualization -->
                        <div id="timeConflictVisual" style="display: none; margin: 1rem 0;">
                            <h4 style="font-size: 0.9rem; color: var(--danger); margin-bottom: 0.5rem;">
                                <i class="fas fa-exclamation-triangle"></i> Time Conflict Detected
                            </h4>
                            <div class="timeline-container">
                                <div class="timeline" id="conflictTimeline">
                                    <!-- Conflict visualization will be added by JavaScript -->
                                </div>
                                <div class="time-labels">
                                    <span>6 AM</span>
                                    <span>12 PM</span>
                                    <span>6 PM</span>
                                    <span>12 AM</span>
                                </div>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary" id="submitBtn">
                                <i class="fas fa-plus"></i> Add Activity
                            </button>
                            <button type="reset" class="btn btn-secondary" id="resetBtn">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
            
            <!-- Weekly Schedule -->
            <div class="weekly-schedule">
                <div class="section-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h3><i class="fas fa-calendar-week"></i> Weekly Schedule <?php echo isAdmin() ? '(All Users)' : ''; ?></h3>
                    <div style="color: var(--gray); font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> Activities cannot overlap on the same day
                    </div>
                </div>
                
                <div class="days-grid">
                    <?php 
                    $current_day = date('l');
                    $current_time = date('H:i');
                    
                    foreach($days as $day): 
                        $is_today = ($day == $current_day);
                    ?>
                    <div class="day-card <?php echo $is_today ? 'today-card' : ''; ?>" id="day-<?php echo strtolower($day); ?>">
                        <h4>
                            <i class="fas fa-calendar-day"></i> <?php echo $day; ?>
                            <?php if($is_today): ?>
                                <span style="font-size: 0.8rem; color: var(--primary); background: rgba(102, 126, 234, 0.1); padding: 2px 8px; border-radius: 10px;">Today</span>
                            <?php endif; ?>
                        </h4>
                        
                        <!-- Timeline for the day -->
                        <?php if(!empty($routines[$day])): ?>
                        <div class="timeline-container">
                            <div class="timeline" id="timeline-<?php echo strtolower($day); ?>">
                                <?php foreach($routines[$day] as $activity): 
                                    $start_percent = (strtotime($activity['start_time']) - strtotime('06:00:00')) / (strtotime('24:00:00') - strtotime('06:00:00')) * 100;
                                    $end_percent = (strtotime($activity['end_time']) - strtotime('06:00:00')) / (strtotime('24:00:00') - strtotime('06:00:00')) * 100;
                                    $width = max(2, $end_percent - $start_percent); // Minimum 2% width
                                ?>
                                <div class="time-slot" 
                                     style="left: <?php echo $start_percent; ?>%; width: <?php echo $width; ?>%;"
                                     title="<?php echo htmlspecialchars($activity['activity']); ?> (<?php echo date('g:i A', strtotime($activity['start_time'])); ?> - <?php echo date('g:i A', strtotime($activity['end_time'])); ?>)">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="time-labels">
                                <span>6 AM</span>
                                <span>12 PM</span>
                                <span>6 PM</span>
                                <span>12 AM</span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="day-activities">
                            <?php if(empty($routines[$day])): ?>
                                <div class="no-activities">
                                    <i class="fas fa-calendar-plus" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                                    <p>No activities scheduled</p>
                                    <?php if(!isAdmin()): ?>
                                        <small>Add activities using the form above</small>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <?php 
                                // Check for time conflicts in existing activities
                                $activities = $routines[$day];
                                $conflicts = [];
                                for($i = 0; $i < count($activities); $i++) {
                                    for($j = $i + 1; $j < count($activities); $j++) {
                                        $a1_start = strtotime($activities[$i]['start_time']);
                                        $a1_end = strtotime($activities[$i]['end_time']);
                                        $a2_start = strtotime($activities[$j]['start_time']);
                                        $a2_end = strtotime($activities[$j]['end_time']);
                                        
                                        // Check for overlap
                                        if (($a1_start < $a2_end && $a1_end > $a2_start) || 
                                            ($a2_start < $a1_end && $a2_end > $a1_start)) {
                                            $conflicts[] = $activities[$i]['id'];
                                            $conflicts[] = $activities[$j]['id'];
                                        }
                                    }
                                }
                                $conflicts = array_unique($conflicts);
                                
                                foreach($routines[$day] as $activity): 
                                    $is_past_activity = $is_today && ($activity['end_time'] < $current_time);
                                    $has_conflict = in_array($activity['id'], $conflicts);
                                ?>
                                <div class="activity-item <?php echo $is_past_activity ? 'past-activity' : ''; ?> <?php echo $has_conflict ? 'conflict' : ''; ?>" 
                                     data-id="<?php echo $activity['id']; ?>"
                                     data-start="<?php echo $activity['start_time']; ?>"
                                     data-end="<?php echo $activity['end_time']; ?>">
                                    <div class="activity-info">
                                        <strong><?php echo htmlspecialchars($activity['activity']); ?></strong>
                                        <?php if(isAdmin() && isset($activity['user_name'])): ?>
                                            <span style="font-size: 0.8rem; color: var(--gray); display: block; margin: 0.25rem 0;">
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($activity['user_name']); ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="activity-time <?php echo $is_past_activity ? 'past-time' : ''; ?>">
                                            <i class="far fa-clock"></i> 
                                            <?php echo date('g:i A', strtotime($activity['start_time'])); ?> - 
                                            <?php echo date('g:i A', strtotime($activity['end_time'])); ?>
                                            <?php if($has_conflict): ?>
                                                <span style="color: var(--danger); font-size: 0.8rem; margin-left: 5px;">
                                                    <i class="fas fa-exclamation-triangle"></i> Time Conflict
                                                </span>
                                            <?php endif; ?>
                                            <?php if($is_past_activity): ?>
                                                <span style="color: var(--gray); font-size: 0.8rem;">
                                                    (Completed)
                                                </span>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if(isAdmin() || (!isAdmin() && $activity['user_id'] == $user_id)): ?>
                                    <form method="POST" class="delete-form">
                                        <input type="hidden" name="delete_id" value="<?php echo $activity['id']; ?>">
                                        <button type="submit" class="btn-delete" onclick="return confirm('Delete this activity?')" title="Delete Activity">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Day summary -->
                        <?php if(!empty($routines[$day])): ?>
                            <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid var(--light-gray); font-size: 0.9rem; color: var(--gray);">
                                <i class="fas fa-list-check"></i> <?php echo count($routines[$day]); ?> activity<?php echo count($routines[$day]) != 1 ? 'ies' : ''; ?> scheduled
                                <?php 
                                $conflict_count = count(array_filter($routines[$day], function($activity) use ($conflicts) {
                                    return in_array($activity['id'], $conflicts);
                                }));
                                if ($conflict_count > 0): ?>
                                    <span style="color: var(--danger); margin-left: 10px;">
                                        <i class="fas fa-exclamation-triangle"></i> <?php echo $conflict_count; ?> conflict<?php echo $conflict_count != 1 ? 's' : ''; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Help Section -->
        <div class="section" style="background: #f8fafc; margin-top: 2rem;">
            <h3><i class="fas fa-question-circle"></i> Time Conflict Rules</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
                <div style="background: white; padding: 1.5rem; border-radius: var(--radius-sm); box-shadow: var(--shadow);">
                    <i class="fas fa-ban" style="color: var(--danger); font-size: 2rem; margin-bottom: 1rem;"></i>
                    <h4>No Overlapping Times</h4>
                    <p style="color: var(--gray); font-size: 0.9rem;">You cannot schedule activities that overlap in time on the same day.</p>
                </div>
                <div style="background: white; padding: 1.5rem; border-radius: var(--radius-sm); box-shadow: var(--shadow);">
                    <i class="fas fa-clock" style="color: var(--primary); font-size: 2rem; margin-bottom: 1rem;"></i>
                    <h4>Sequential Scheduling</h4>
                    <p style="color: var(--gray); font-size: 0.9rem;">Activities must be scheduled one after another with clear time gaps.</p>
                </div>
                <div style="background: white; padding: 1.5rem; border-radius: var(--radius-sm); box-shadow: var(--shadow);">
                    <i class="fas fa-eye" style="color: var(--warning); font-size: 2rem; margin-bottom: 1rem;"></i>
                    <h4>Visual Timeline</h4>
                    <p style="color: var(--gray); font-size: 0.9rem;">Use the timeline above each day to see scheduled time slots.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Pass PHP data to JavaScript
        const allActivities = <?php echo json_encode($allActivities); ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            const daySelect = document.getElementById('day_of_week');
            const startTimeInput = document.getElementById('start_time');
            const endTimeInput = document.getElementById('end_time');
            const form = document.getElementById('routineForm');
            const submitBtn = document.getElementById('submitBtn');
            const conflictNote = document.getElementById('conflictNote');
            const conflictMessage = document.getElementById('conflictMessage');
            const timeConflictVisual = document.getElementById('timeConflictVisual');
            const conflictTimeline = document.getElementById('conflictTimeline');
            
            // Get current day and time
            const currentDay = '<?php echo date("l"); ?>';
            const currentTime = '<?php echo date("H:i"); ?>';
            
            // Function to check for time conflicts
            function checkTimeConflicts(day, startTime, endTime) {
                const activities = allActivities[day] || [];
                const start = timeToMinutes(startTime);
                const end = timeToMinutes(endTime);
                
                // Check each activity for overlap
                for (const activity of activities) {
                    const activityStart = timeToMinutes(activity.start_time);
                    const activityEnd = timeToMinutes(activity.end_time);
                    
                    // Check for overlap (touching is allowed, overlapping is not)
                    if ((start < activityEnd && end > activityStart)) {
                        return {
                            hasConflict: true,
                            conflictActivity: activity,
                            conflictStart: minutesToTime(activityStart),
                            conflictEnd: minutesToTime(activityEnd)
                        };
                    }
                }
                
                return { hasConflict: false };
            }
            
            // Convert time string to minutes
            function timeToMinutes(timeStr) {
                const [hours, minutes] = timeStr.split(':').map(Number);
                return hours * 60 + minutes;
            }
            
            // Convert minutes to time string
            function minutesToTime(minutes) {
                const hours = Math.floor(minutes / 60);
                const mins = minutes % 60;
                return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
            }
            
            // Format time for display
            function formatTimeForDisplay(timeStr) {
                const [hours, minutes] = timeStr.split(':').map(Number);
                const ampm = hours >= 12 ? 'PM' : 'AM';
                const displayHours = hours % 12 || 12;
                return `${displayHours}:${minutes.toString().padStart(2, '0')} ${ampm}`;
            }
            
            // Visualize time conflicts
            function visualizeConflict(day, startTime, endTime, conflictActivity) {
                timeConflictVisual.style.display = 'block';
                conflictTimeline.innerHTML = '';
                
                // Calculate positions for timeline
                const startMinutes = timeToMinutes(startTime);
                const endMinutes = timeToMinutes(endTime);
                const conflictStart = timeToMinutes(conflictActivity.start_time);
                const conflictEnd = timeToMinutes(conflictActivity.end_time);
                
                // Convert to percentages (6 AM to 12 AM = 18 hours)
                const startPercent = ((startMinutes - 360) / 1080) * 100;
                const endPercent = ((endMinutes - 360) / 1080) * 100;
                const conflictStartPercent = ((conflictStart - 360) / 1080) * 100;
                const conflictEndPercent = ((conflictEnd - 360) / 1080) * 100;
                
                // Width calculations
                const newWidth = Math.max(2, endPercent - startPercent);
                const conflictWidth = Math.max(2, conflictEndPercent - conflictStartPercent);
                
                // Create conflict marker (overlap area)
                const overlapStart = Math.max(startPercent, conflictStartPercent);
                const overlapEnd = Math.min(endPercent, conflictEndPercent);
                const overlapWidth = Math.max(2, overlapEnd - overlapStart);
                
                if (overlapWidth > 0) {
                    // Add overlap area
                    const overlapDiv = document.createElement('div');
                    overlapDiv.className = 'time-slot conflict-warning';
                    overlapDiv.style.left = overlapStart + '%';
                    overlapDiv.style.width = overlapWidth + '%';
                    overlapDiv.style.background = 'rgba(245, 101, 101, 0.7)';
                    overlapDiv.title = 'Overlap Area';
                    conflictTimeline.appendChild(overlapDiv);
                }
                
                // Add new activity slot
                const newDiv = document.createElement('div');
                newDiv.className = 'time-slot';
                newDiv.style.left = startPercent + '%';
                newDiv.style.width = newWidth + '%';
                newDiv.style.background = 'rgba(102, 126, 234, 0.5)';
                newDiv.title = `New: ${formatTimeForDisplay(startTime)} - ${formatTimeForDisplay(endTime)}`;
                conflictTimeline.appendChild(newDiv);
                
                // Add conflicting activity slot
                const conflictDiv = document.createElement('div');
                conflictDiv.className = 'time-slot';
                conflictDiv.style.left = conflictStartPercent + '%';
                conflictDiv.style.width = conflictWidth + '%';
                conflictDiv.style.background = 'rgba(237, 137, 54, 0.5)';
                conflictDiv.title = `Existing: ${formatTimeForDisplay(conflictActivity.start_time)} - ${formatTimeForDisplay(conflictActivity.end_time)}: ${conflictActivity.activity}`;
                conflictTimeline.appendChild(conflictDiv);
            }
            
            // Function to validate and check conflicts in real-time
            function validateAndCheckConflicts() {
                const selectedDay = daySelect.value;
                const startTime = startTimeInput.value;
                const endTime = endTimeInput.value;
                
                if (!startTime || !endTime || startTime >= endTime) {
                    conflictNote.style.display = 'none';
                    timeConflictVisual.style.display = 'none';
                    submitBtn.disabled = true;
                    return;
                }
                
                const conflictCheck = checkTimeConflicts(selectedDay, startTime, endTime);
                
                if (conflictCheck.hasConflict) {
                    conflictNote.style.display = 'block';
                    conflictMessage.textContent = `This overlaps with "${conflictCheck.conflictActivity.activity}" (${formatTimeForDisplay(conflictCheck.conflictStart)} - ${formatTimeForDisplay(conflictCheck.conflictEnd)})`;
                    
                    // Visualize the conflict
                    visualizeConflict(selectedDay, startTime, endTime, conflictCheck.conflictActivity);
                    
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-ban"></i> Time Conflict';
                    submitBtn.style.background = 'var(--danger)';
                } else {
                    conflictNote.style.display = 'none';
                    timeConflictVisual.style.display = 'none';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-plus"></i> Add Activity';
                    submitBtn.style.background = '';
                }
            }
            
            // Function to set minimum time based on selected day
            function updateTimeRestrictions() {
                const selectedDay = daySelect.value;
                
                if (selectedDay === currentDay) {
                    // If selecting today, set minimum time to current time + 5 minutes
                    const now = new Date();
                    now.setMinutes(now.getMinutes() + 5); // Allow scheduling 5 minutes from now
                    
                    // Format time as HH:MM
                    const hours = now.getHours().toString().padStart(2, '0');
                    const minutes = now.getMinutes().toString().padStart(2, '0');
                    const minTime = `${hours}:${minutes}`;
                    
                    startTimeInput.min = minTime;
                    endTimeInput.min = minTime;
                    
                    // If current time is later than the input value, update it
                    if (startTimeInput.value < minTime) {
                        startTimeInput.value = minTime;
                        // Set end time to 1 hour after start time
                        const endTime = new Date(now);
                        endTime.setHours(endTime.getHours() + 1);
                        const endHours = endTime.getHours().toString().padStart(2, '0');
                        const endMinutes = endTime.getMinutes().toString().padStart(2, '0');
                        endTimeInput.value = `${endHours}:${endMinutes}`;
                    }
                } else {
                    // If selecting another day, remove time restrictions
                    startTimeInput.removeAttribute('min');
                    endTimeInput.removeAttribute('min');
                }
                
                // Check for conflicts after changing day
                validateAndCheckConflicts();
            }
            
            // Update restrictions when day changes
            daySelect.addEventListener('change', updateTimeRestrictions);
            
            // Validate end time is after start time
            startTimeInput.addEventListener('change', function() {
                validateTime();
                validateAndCheckConflicts();
            });
            
            endTimeInput.addEventListener('change', function() {
                validateTime();
                validateAndCheckConflicts();
            });
            
            function validateTime() {
                if (startTimeInput.value && endTimeInput.value) {
                    if (startTimeInput.value >= endTimeInput.value) {
                        endTimeInput.setCustomValidity('End time must be after start time');
                        endTimeInput.style.borderColor = '#f56565';
                        endTimeInput.style.boxShadow = '0 0 0 2px rgba(245, 101, 101, 0.1)';
                        submitBtn.disabled = true;
                    } else {
                        endTimeInput.setCustomValidity('');
                        endTimeInput.style.borderColor = '';
                        endTimeInput.style.boxShadow = '';
                    }
                }
            }
            
            // Set initial restrictions
            updateTimeRestrictions();
            
            // Form validation
            if (form) {
                form.addEventListener('submit', function(e) {
                    const selectedDay = daySelect.value;
                    const selectedTime = startTimeInput.value;
                    
                    // Check if user is admin (admins can schedule past times)
                    const isAdmin = <?php echo isAdmin() ? 'true' : 'false'; ?>;
                    
                    // Check for time conflicts one more time before submitting
                    const conflictCheck = checkTimeConflicts(selectedDay, startTimeInput.value, endTimeInput.value);
                    if (conflictCheck.hasConflict) {
                        e.preventDefault();
                        alert(`Time conflict! This overlaps with "${conflictCheck.conflictActivity.activity}" (${formatTimeForDisplay(conflictCheck.conflictStart)} - ${formatTimeForDisplay(conflictCheck.conflictEnd)}). Please choose a different time.`);
                        return false;
                    }
                    
                    // Only validate for non-admin users
                    if (!isAdmin && selectedDay === currentDay && selectedTime < currentTime) {
                        e.preventDefault();
                        alert('Cannot schedule routine activities in the past. Please select a time after ' + 
                              new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) + 
                              ' for today.');
                        return false;
                    }
                    
                    return true;
                });
            }
            
            // Initialize time validation
            validateTime();
            
            // Reset button
            document.getElementById('resetBtn').addEventListener('click', function() {
                setTimeout(() => {
                    updateTimeRestrictions();
                    validateAndCheckConflicts();
                }, 100);
            });
            
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
            
            // Highlight today's card
            const todayCard = document.querySelector('.today-card');
            if (todayCard) {
                todayCard.style.animation = 'pulse 2s infinite';
                todayCard.style.boxShadow = '0 4px 20px rgba(102, 126, 234, 0.2)';
            }
        });
    </script>
</body>
</html>