<?php
// Include configuration and database connection
require_once 'includes/config.php';
require_once 'includes/db-connection.php';

// Session is already started in config.php, so don't call session_start() here

// Check if user is already logged in, redirect accordingly
if (isset($_SESSION['user_id'])) {
    // If student is logged in, redirect to student dashboard
    header("Location: student/dashboard.php");
    exit();
} elseif (isset($_SESSION['admin_id'])) {
    // If admin is logged in, redirect to admin dashboard
    header("Location: admin/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PolyEduHub - Collaborative Learning Platform for Polytechnic Malaysia</title>
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="assets/img/favicon.png" type="image/x-icon">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/styles.css">
    
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #1cc88a;
            --dark-color: #5a5c69;
            --light-color: #f8f9fc;
        }
        
        body {
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: var(--light-color);
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            padding: 100px 0;
            min-height: 70vh;
            display: flex;
            align-items: center;
        }
        
        .feature-box {
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            height: 100%;
            background-color: white;
        }
        
        .feature-box:hover {
            transform: translateY(-10px);
        }
        
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: var(--primary-color);
        }
        
        .auth-card {
            border-radius: 10px;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
            background-color: white;
            padding: 30px;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-success {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .login-nav .nav-link {
            color: var(--dark-color);
            padding: 15px 30px;
            border-radius: 10px 10px 0 0;
        }
        
        .login-nav .nav-link.active {
            background-color: white;
            border-bottom: none;
            font-weight: bold;
        }
        
        .login-tabs {
            margin-top: -1px;
        }
        
        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 40px 0 20px;
        }
        
        .stats-box {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary-color);
        }
        
        .heading-underline {
            position: relative;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        
        .heading-underline::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            height: 4px;
            width: 60px;
            background-color: var(--secondary-color);
        }
        
        .heading-underline.text-center::after {
            left: 50%;
            transform: translateX(-50%);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white py-3 shadow">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="assets/img/polyeduhub-logo.png" alt="PolyEduHub Logo" height="40">
                <span class="ms-2 fw-bold text-primary">PolyEduHub</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="faq.php">FAQ</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="login.php" class="btn btn-outline-primary me-2">Login</a>
                    <a href="register.php" class="btn btn-primary">Register</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <h1 class="display-4 fw-bold mb-4">Collaborate, Learn, Excel</h1>
                    <p class="lead mb-4">A centralized platform for Polytechnic Sultan Abdul Halim Muadzam Shah - Information Technology students to access, share, and collaborate on educational resources.</p>
                    <div class="d-flex flex-wrap">
                        <a href="register.php" class="btn btn-light btn-lg me-3 mb-3">Get Started</a>
                        <a href="#features" class="btn btn-outline-light btn-lg mb-3">Learn More</a>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="auth-card">
                        <ul class="nav nav-tabs login-nav mb-3" id="authTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="student-tab" data-bs-toggle="tab" data-bs-target="#student" type="button" role="tab" aria-controls="student" aria-selected="true">
                                    <i class="fas fa-user-graduate me-2"></i>Student
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="admin-tab" data-bs-toggle="tab" data-bs-target="#admin" type="button" role="tab" aria-controls="admin" aria-selected="false">
                                    <i class="fas fa-user-shield me-2"></i>Admin
                                </button>
                            </li>
                        </ul>
                        <div class="tab-content login-tabs" id="authTabContent">
                            <!-- Student Login Tab -->
                            <div class="tab-pane fade show active" id="student" role="tabpanel" aria-labelledby="student-tab">
                                <div class="d-grid gap-3">
                                    <a href="login.php" class="btn btn-primary btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i>Student Login
                                    </a>
                                    <a href="register.php" class="btn btn-success btn-lg">
                                        <i class="fas fa-user-plus me-2"></i>Student Register
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Admin Login Tab -->
                            <div class="tab-pane fade" id="admin" role="tabpanel" aria-labelledby="admin-tab">
                                <div class="d-grid gap-3">
                                    <a href="admin-login.php" class="btn btn-primary btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i>Admin Login
                                    </a>
                                    <a href="admin-register.php" class="btn btn-success btn-lg">
                                        <i class="fas fa-user-plus me-2"></i>Admin Register
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="stats-box">
                        <div class="stats-number">10+</div>
                        <div>Resources Shared</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stats-box">
                        <div class="stats-number">"10+"</div>
                        <div>Active Users</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stats-box">
                        <div class="stats-number">Information Technology - Digital Technology </div>
                        <div>Departments</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stats-box">
                        <div class="stats-number">Polytechnic Sultan Abdul Halim Muadzam Shah</div>
                        <div>Polytechnic Institution</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-5 bg-white" id="features">
        <div class="container">
            <h2 class="text-center heading-underline">Key Features</h2>
            <div class="row">
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <h4>Resource Repository</h4>
                        <p>Access and share notes, assignments, and activities. Organize everything with tags for easy navigation.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4>Collaborative Learning</h4>
                        <p>Work together in real-time with chat features and document collaboration tools.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-search"></i>
                        </div>
                        <h4>Smart Search</h4>
                        <p>Find exactly what you need with advanced search filters by subject, course, or keywords.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <h4>Reward System</h4>
                        <p>Earn points, badges, and climb the leaderboard by sharing quality resources and helping others.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-user-circle"></i>
                        </div>
                        <h4>Personalization</h4>
                        <p>Get tailored recommendations based on your preferences and academic needs.</p>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="feature-box">
                        <div class="feature-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h4>Secure Platform</h4>
                        <p>Your data and contributions are secure with our robust authentication system.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call to Action -->
    <section class="py-5 bg-primary text-white text-center">
        <div class="container">
            <h2 class="mb-4">Ready to enhance your learning experience?</h2>
            <p class="lead mb-4">Join thousands of polytechnic students already using PolyEduHub</p>
            <a href="register.php" class="btn btn-light btn-lg px-5">Sign Up Now</a>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5 class="mb-3">PolyEduHub</h5>
                    <p>A centralized platform for Polytechnic Sultan Abdul Halim Muadzam Shah - Information Technology students to access, share, and collaborate on educational resources.</p>
                    <div class="social-links">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-md-2 mb-4 mb-md-0">
                    <h5 class="mb-3">Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="index.php" class="text-white text-decoration-none">Home</a></li>
                        <li><a href="about.php" class="text-white text-decoration-none">About</a></li>
                        <li><a href="contact.php" class="text-white text-decoration-none">Contact</a></li>
                        <li><a href="faq.php" class="text-white text-decoration-none">FAQs</a></li>
                    </ul>
                </div>
                <div class="col-md-2 mb-4 mb-md-0">
                    <h5 class="mb-3">Support</h5>
                    <ul class="list-unstyled">
                        <li><a href="help.php" class="text-white text-decoration-none">Help Center</a></li>
                        <li><a href="terms.php" class="text-white text-decoration-none">Terms of Service</a></li>
                        <li><a href="privacy.php" class="text-white text-decoration-none">Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5 class="mb-3">Contact</h5>
                    <p><i class="fas fa-map-marker-alt me-2"></i> Polytechnic Malaysia, Kuala Lumpur</p>
                    <p><i class="fas fa-envelope me-2"></i> info@polyeduhub.edu.my</p>
                    <p><i class="fas fa-phone me-2"></i> +60 12 345 6789</p>
                </div>
            </div>
            <hr class="mt-4 mb-3 bg-light">
            <div class="row">
                <div class="col-md-12 text-center">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> PolyEduHub. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="assets/js/scripts.js"></script>
</body>
</html>