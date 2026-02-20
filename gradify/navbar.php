<?php
// navbar.php - Desktop only version
?>
<nav class="navbar">
    <div class="container nav-container">
        <div class="logo">
            <a href="index.php">
                <i class="fas fa-graduation-cap"></i>
                <h1>Gradify</h1>
            </a>
        </div>
        
        <ul class="nav-links">
            <li><a href="index.php" <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>
                <i class="fas fa-home"></i> Home
            </a></li>
            
            <?php if(isLoggedIn()): ?>
                <li><a href="dashboard.php" <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a></li>
                
                <li><a href="plans.php" <?php echo basename($_SERVER['PHP_SELF']) == 'plans.php' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-calendar-alt"></i> Plans
                </a></li>
                
                <li><a href="routines.php" <?php echo basename($_SERVER['PHP_SELF']) == 'routines.php' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-tasks"></i> Routines
                </a></li>
                
                <li><a href="timer.php" <?php echo basename($_SERVER['PHP_SELF']) == 'timer.php' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-clock"></i> Timer
                </a></li>
                
                <?php if(isAdmin()): ?>
                    <li><a href="admin.php" <?php echo basename($_SERVER['PHP_SELF']) == 'admin.php' ? 'class="active"' : ''; ?>>
                        <i class="fas fa-user-shield"></i> Admin Panel
                    </a></li>
                <?php endif; ?>
                
                <li class="user-menu">
                    <span class="user-info">
                        <i class="fas fa-user-circle"></i> 
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                        <?php if(isAdmin()): ?>
                            <span style="background: #667eea; color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.7rem; margin-left: 5px;">Admin</span>
                        <?php endif; ?>
                    </span>
                    <div class="dropdown">
                        <a href="logout.php" style="color: var(--danger);">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </li>
            <?php else: ?>
                <li><a href="login.php" <?php echo basename($_SERVER['PHP_SELF']) == 'login.php' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-sign-in-alt"></i> Login
                </a></li>
                
                <li><a href="register.php" <?php echo basename($_SERVER['PHP_SELF']) == 'register.php' ? 'class="active"' : ''; ?>>
                    <i class="fas fa-user-plus"></i> Register
                </a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>