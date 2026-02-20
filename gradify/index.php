<?php
require_once 'database.php';

// Check if database setup message needs to be shown
if (isset($_SESSION['database_setup'])) {
    $setup_message = $_SESSION['database_setup'];
    unset($_SESSION['database_setup']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gradify - Study Planner</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .setup-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-sm);
            box-shadow: var(--shadow-lg);
            max-width: 400px;
            animation: slideIn 0.5s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }
        
        .feature-card {
            background: white;
            padding: 2.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
            border-top: 5px solid var(--primary);
        }
        
        .feature-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--shadow-hover);
        }
        
        .feature-card i {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        
        .feature-card h3 {
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .feature-card p {
            color: var(--gray);
            line-height: 1.6;
        }
        
        .hero-section {
            text-align: center;
            padding: 5rem 0 3rem;
        }
        
        .hero-section h1 {
            font-size: 3.5rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 1.5rem;
            line-height: 1.2;
        }
        
        .hero-section p {
            font-size: 1.3rem;
            color: var(--gray);
            max-width: 700px;
            margin: 0 auto 2.5rem;
            line-height: 1.6;
        }
        
        .cta-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2.5rem;
            flex-wrap: wrap;
        }
        
        .testimonials {
            background: white;
            padding: 4rem 2rem;
            border-radius: var(--radius);
            margin: 4rem 0;
            box-shadow: var(--shadow);
        }
        
        .testimonial-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .testimonial-card {
            background: #f8fafc;
            padding: 2rem;
            border-radius: var(--radius);
            border-left: 4px solid var(--primary);
        }
        
        .testimonial-text {
            font-style: italic;
            color: var(--dark);
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .testimonial-author {
            font-weight: 600;
            color: var(--primary);
        }
        
        .stats-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin: 4rem 0;
            text-align: center;
        }
        
        .stat-item h3 {
            font-size: 2.5rem;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            margin-bottom: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .hero-section h1 {
                font-size: 2.5rem;
            }
            
            .hero-section p {
                font-size: 1.1rem;
            }
            
            .feature-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php if(isset($setup_message)): ?>
    <div class="setup-message" id="setupMessage">
        <i class="fas fa-check-circle"></i> <?php echo $setup_message; ?>
        <button onclick="document.getElementById('setupMessage').remove()" 
                style="background: none; border: none; color: white; cursor: pointer; margin-left: 10px; float: right;">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <script>
        setTimeout(() => {
            const msg = document.getElementById('setupMessage');
            if (msg) msg.remove();
        }, 10000);
    </script>
    <?php endif; ?>
    
    <nav class="navbar">
        <div class="container nav-container">
            <div class="logo">
                <a href="index.php">
                    <i class="fas fa-graduation-cap"></i>
                    <h1>Gradify</h1>
                </a>
            </div>
            <ul class="nav-links">
                <li><a href="index.php" class="active"><i class="fas fa-home"></i> Home</a></li>
                <?php if(isLoggedIn()): ?>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="plans.php"><i class="fas fa-calendar-alt"></i> Plans</a></li>
                    <li><a href="routines.php"><i class="fas fa-tasks"></i> Routines</a></li>
                    <li><a href="timer.php"><i class="fas fa-clock"></i> Timer</a></li>
                    <?php if(isAdmin()): ?>
                        <li><a href="admin.php"><i class="fas fa-user-shield"></i> Admin</a></li>
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
                    <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                    <li><a href="register.php"><i class="fas fa-user-plus"></i> Register</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <div class="container">
        <!-- Hero Section -->
        <div class="hero-section">
            <h1>Welcome to Gradify</h1>
            <p>Your personal study planner for academic success. Organize, track, and optimize your study sessions with intelligent scheduling and productivity tools.</p>
            
            <?php if(!isLoggedIn()): ?>
                <div class="cta-buttons">
                    <a href="register.php" class="btn btn-primary" style="padding: 1rem 2.5rem; font-size: 1.1rem;">
                        <i class="fas fa-rocket"></i> Get Started Free
                    </a>
                    <a href="login.php" class="btn btn-secondary" style="padding: 1rem 2.5rem; font-size: 1.1rem;">
                        <i class="fas fa-sign-in-alt"></i> Student Login
                    </a>
                </div>
                
                <div class="stats-section">
                    <div class="stat-item">
                        <h3>500+</h3>
                        <p>Students Using</p>
                    </div>
                    <div class="stat-item">
                        <h3>95%</h3>
                        <p>Improved Productivity</p>
                    </div>
                    <div class="stat-item">
                        <h3>4.8/5</h3>
                        <p>User Rating</p>
                    </div>
                    <div class="stat-item">
                        <h3>24/7</h3>
                        <p>Always Available</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="cta-buttons">
                    <a href="dashboard.php" class="btn btn-primary" style="padding: 1rem 2.5rem; font-size: 1.1rem;">
                        <i class="fas fa-tachometer-alt"></i> Go to Dashboard
                    </a>
                    <a href="plans.php?action=add" class="btn btn-secondary" style="padding: 1rem 2.5rem; font-size: 1.1rem;">
                        <i class="fas fa-calendar-plus"></i> Create New Plan
                    </a>
                    <a href="timer.php" class="btn" style="background: var(--success); color: white; padding: 1rem 2.5rem; font-size: 1.1rem;">
                        <i class="fas fa-stopwatch"></i> Start Timer
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Features Section -->
        <div class="features">
            <h2 style="text-align: center; margin-bottom: 1rem; font-size: 2.5rem; color: var(--dark);">Everything You Need to Study Smart</h2>
            <p style="text-align: center; color: var(--gray); font-size: 1.2rem; max-width: 700px; margin: 0 auto 2rem;">
                Powerful tools designed to help you maximize your study efficiency and achieve academic goals
            </p>
            
            <div class="feature-grid">
                <div class="feature-card">
                    <i class="fas fa-calendar-plus"></i>
                    <h3>Smart Study Plans</h3>
                    <p>Schedule and organize your study sessions with intelligent time blocking and progress tracking.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-tasks"></i>
                    <h3>Daily Routines</h3>
                    <p>Create consistent daily study routines that build productive habits and optimize your learning schedule.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-stopwatch"></i>
                    <h3>Focus Timer</h3>
                    <p>Pomodoro timer with customizable intervals to maintain concentration and prevent burnout.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-chart-line"></i>
                    <h3>Progress Analytics</h3>
                    <p>Monitor your study habits, track completion rates, and visualize your academic progress over time.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-bell"></i>
                    <h3>Smart Reminders</h3>
                    <p>Never miss a study session with intelligent notifications and calendar integration.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-mobile-alt"></i>
                    <h3>Always Accessible</h3>
                    <p>Access your study plans from any device, anywhere, with cloud synchronization.</p>
                </div>
            </div>
        </div>

        <?php if(!isLoggedIn()): ?>
        <!-- Testimonials Section -->
        <div class="testimonials">
            <h2 style="text-align: center; margin-bottom: 1rem; color: var(--dark);">What Students Are Saying</h2>
            <p style="text-align: center; color: var(--gray); margin-bottom: 2rem;">Join thousands of successful students who transformed their study habits</p>
            
            <div class="testimonial-grid">
                <div class="testimonial-card">
                    <p class="testimonial-text">"Gradify helped me organize my study schedule and increased my productivity by 300%. My grades have never been better!"</p>
                    <p class="testimonial-author">— Sarah Johnson, Medical Student</p>
                </div>
                <div class="testimonial-card">
                    <p class="testimonial-text">"The Pomodoro timer is a game-changer. I can focus for longer periods without feeling overwhelmed. Highly recommended!"</p>
                    <p class="testimonial-author">— Michael Chen, Engineering Student</p>
                </div>
                <div class="testimonial-card">
                    <p class="testimonial-text">"Finally, a study planner that actually works! The routine builder helped me establish consistent study habits."</p>
                    <p class="testimonial-author">— Emma Williams, Law Student</p>
                </div>
            </div>
        </div>

        <!-- Final CTA Section -->
        <div style="text-align: center; padding: 4rem 2rem; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: var(--radius); color: white; margin: 4rem 0;">
            <h2 style="color: white; margin-bottom: 1rem;">Ready to Transform Your Study Habits?</h2>
            <p style="color: rgba(255,255,255,0.9); max-width: 600px; margin: 0 auto 2rem; font-size: 1.2rem;">
                Join thousands of students who have improved their grades and reduced study stress with Gradify.
            </p>
            <div class="cta-buttons">
                <a href="register.php" class="btn" style="background: white; color: var(--primary); padding: 1rem 3rem; font-size: 1.2rem; font-weight: 600;">
                    <i class="fas fa-user-plus"></i> Sign Up Free
                </a>
                <a href="login.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; border: 2px solid white; padding: 1rem 3rem; font-size: 1.2rem; font-weight: 600;">
                    <i class="fas fa-sign-in-alt"></i> Login
                </a>
            </div>
            <p style="margin-top: 2rem; font-size: 0.9rem; color: rgba(255,255,255,0.8);">
                No credit card required • Free forever for basic features
            </p>
        </div>
        <?php endif; ?>
    </div>

    <footer class="footer">
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
                <div>
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 1rem;">
                        <i class="fas fa-graduation-cap" style="font-size: 2rem; color: var(--primary);"></i>
                        <h2 style="font-size: 1.5rem; margin: 0; background: var(--gradient-primary); -webkit-background-clip: text; background-clip: text; color: transparent;">Gradify</h2>
                    </div>
                    <p style="color: var(--gray); max-width: 300px;">Making studying smarter, not harder. Your partner in academic success.</p>
                </div>
                
                <div>
                    <h3 style="color: var(--dark); margin-bottom: 1rem;">Quick Links</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <a href="index.php" style="color: var(--gray); text-decoration: none;">Home</a>
                        <?php if(!isLoggedIn()): ?>
                            <a href="login.php" style="color: var(--gray); text-decoration: none;">Login</a>
                            <a href="register.php" style="color: var(--gray); text-decoration: none;">Register</a>
                        <?php else: ?>
                            <a href="dashboard.php" style="color: var(--gray); text-decoration: none;">Dashboard</a>
                            <a href="plans.php" style="color: var(--gray); text-decoration: none;">Study Plans</a>
                            <a href="timer.php" style="color: var(--gray); text-decoration: none;">Focus Timer</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div style="border-top: 1px solid var(--light-gray); margin-top: 2rem; padding-top: 1.5rem; text-align: center;">
                <p style="color: var(--gray);">&copy; <?php echo date('Y'); ?> Gradify Study Planner. All rights reserved.</p>
                <p style="color: var(--gray); font-size: 0.9rem; margin-top: 0.5rem;">
                    <i class="fas fa-heart" style="color: #f56565;"></i> Made with love for students everywhere
                </p>
            </div>
        </div>
    </footer>

    <script>
        // Auto-hide setup message after 10 seconds
        setTimeout(() => {
            const setupMsg = document.getElementById('setupMessage');
            if (setupMsg) {
                setupMsg.style.opacity = '0';
                setupMsg.style.transition = 'opacity 0.5s';
                setTimeout(() => {
                    if (setupMsg.parentNode) {
                        setupMsg.remove();
                    }
                }, 500);
            }
        }, 10000);
        
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });
    </script>
</body>
</html>