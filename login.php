<?php
// login.php - Updated Version
ob_start();
session_start();
require_once('conn_inc.php');

// If user is already logged in, redirect to index
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // Validate inputs
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = "Please enter both username and password";
        header("Location: login.php");
        exit();
    }

    // Query for active users (status = 1)
    $sql = "SELECT id, role_id, username, password FROM users WHERE username = ? AND status = 0";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();

        // Verify password (in production, use password_verify() with hashed passwords)
        if ($row['password'] === $password) {
            // Set session variables
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role_id'] = $row['role_id'];
            $_SESSION['canary'] = time(); // Security timestamp
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Clear buffer and redirect
            ob_end_clean();
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "❌ Incorrect password.";
            ob_end_clean();
            header("Location: login.php");
            exit();
        }
    } else {
        $_SESSION['error'] = "❌ User not found or inactive.";
        ob_end_clean();
        header("Location: login.php");
        exit();
    }
}

// If not POST request, show login form
ob_end_clean();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Madrassa Al-Farooqia</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Noto+Nastaliq+Urdu&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Poppins', sans-serif;
            padding: 15px;
            position: relative;
            overflow: hidden;
        }

        /* Background Image Container */
        .bg-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('https://static.vecteezy.com/system/resources/thumbnails/045/711/504/small/an-ornate-islamic-archway-and-glowing-lantern-against-a-blue-textured-wall-capturing-the-spirit-of-the-islamic-new-year-photo.JPG');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            z-index: -2;
            transition: transform 0.2s ease-out;
        }

        /* Dark overlay for better text readability */
        .bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, 
                rgba(5, 30, 50, 0.88) 0%, 
                rgba(5, 30, 50, 0.75) 50%, 
                rgba(10, 50, 35, 0.8) 100%);
            z-index: -1;
        }

        /* Subtle light particles */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 50%;
            animation: floatUp 8s infinite;
        }

        .particle:nth-child(1) { left: 10%; width: 2px; height: 2px; animation-delay: 0s; animation-duration: 6s; }
        .particle:nth-child(2) { left: 20%; width: 3px; height: 3px; animation-delay: 1s; animation-duration: 8s; }
        .particle:nth-child(3) { left: 35%; width: 2px; height: 2px; animation-delay: 2s; animation-duration: 7s; }
        .particle:nth-child(4) { left: 50%; width: 3px; height: 3px; animation-delay: 3s; animation-duration: 9s; }
        .particle:nth-child(5) { left: 65%; width: 2px; height: 2px; animation-delay: 4s; animation-duration: 6.5s; }
        .particle:nth-child(6) { left: 75%; width: 3px; height: 3px; animation-delay: 5s; animation-duration: 7.5s; }
        .particle:nth-child(7) { left: 85%; width: 2px; height: 2px; animation-delay: 2.5s; animation-duration: 8.5s; }
        .particle:nth-child(8) { left: 90%; width: 3px; height: 3px; animation-delay: 6s; animation-duration: 6s; }
        .particle:nth-child(9) { left: 15%; width: 2px; height: 2px; animation-delay: 3.5s; animation-duration: 9.5s; }
        .particle:nth-child(10) { left: 45%; width: 3px; height: 3px; animation-delay: 1.5s; animation-duration: 7s; }

        @keyframes floatUp {
            0% {
                bottom: -20px;
                opacity: 0;
                transform: translateX(0);
            }
            10% {
                opacity: 0.6;
            }
            90% {
                opacity: 0.1;
            }
            100% {
                bottom: 100%;
                opacity: 0;
                transform: translateX(30px);
            }
        }

        /* Soft lantern glow effect */
        .lantern-glow {
            position: fixed;
            top: 30%;
            right: 15%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            pointer-events: none;
            z-index: -1;
            animation: pulseGlow 4s ease-in-out infinite;
        }

        @keyframes pulseGlow {
            0%, 100% { 
                opacity: 0.4;
                transform: scale(1);
            }
            50% { 
                opacity: 0.8;
                transform: scale(1.15);
            }
        }

        .login-container {
            max-width: 450px;
            width: 100%;
            background: rgba(255, 255, 255, 0.93);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            box-shadow: 0 15px 50px rgba(0, 0, 0, 0.4), 
                        0 0 80px rgba(255, 255, 255, 0.05);
            padding: 2.5rem;
            text-align: center;
            animation: fadeInUp 1s ease-out;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        @keyframes fadeInUp {
            from { 
                transform: translateY(40px); 
                opacity: 0; 
            }
            to { 
                transform: translateY(0); 
                opacity: 1; 
            }
        }

        /* Subtle border decoration */
        .login-container::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, 
                #1a5c3a, 
                transparent 30%, 
                transparent 70%, 
                #1a5c3a);
            border-radius: 22px;
            z-index: -1;
            opacity: 0.3;
        }

        .logo {
            max-width: 90px;
            margin-bottom: 1rem;
            transition: all 0.4s ease;
            border-radius: 50%;
            border: 3px solid #1a5c3a;
            padding: 4px;
            background: linear-gradient(135deg, #0a3d62, #1a5c3a);
            box-shadow: 0 5px 20px rgba(26, 92, 58, 0.2);
        }

        .logo:hover {
            transform: scale(1.08) rotate(5deg);
            box-shadow: 0 8px 30px rgba(26, 92, 58, 0.3);
        }

        .bismillah {
            font-family: 'Noto Nastaliq Urdu', serif;
            font-size: 1.4rem;
            color: #1a5c3a;
            margin-bottom: 0.8rem;
            opacity: 0.9;
            text-shadow: 0 2px 4px rgba(0,0,0,0.05);
            letter-spacing: 1px;
        }

        .madrassa-name {
            font-family: 'Noto Nastaliq Urdu', serif;
            font-size: 1.7rem;
            font-weight: 700;
            color: #0a3d62;
            margin-bottom: 0.3rem;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.08);
        }

        .madrassa-address {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        .error {
            background: linear-gradient(135deg, #fff5f5, #ffe8e8);
            color: #721c24;
            padding: 0.85rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            border-left: 4px solid #e74c3c;
            text-align: left;
            animation: shake 0.5s ease-in-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-8px); }
            75% { transform: translateX(8px); }
        }

        .form-control {
            border: 2px solid #e0e5ec;
            border-radius: 10px;
            padding: 0.85rem 0.85rem 0.85rem 3rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            width: 100%;
            background: rgba(255, 255, 255, 0.95);
            color: #333;
        }

        .form-control:focus {
            border-color: #1a5c3a;
            box-shadow: 0 0 0 4px rgba(26, 92, 58, 0.08), 0 0 15px rgba(26, 92, 58, 0.05);
            outline: none;
            background: #ffffff;
        }

        .form-control::placeholder {
            color: #adb5bd;
        }

        .btn-primary {
            background: linear-gradient(135deg, #0a3d62 0%, #1a5c3a 100%);
            border: none;
            border-radius: 10px;
            padding: 0.9rem;
            font-size: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            transition: all 0.4s ease;
            width: 100%;
            letter-spacing: 2px;
            color: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 5px 20px rgba(10, 61, 98, 0.3);
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .btn-primary:hover::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #1a5c3a 0%, #0a3d62 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(10, 61, 98, 0.4);
            color: #ffffff;
        }

        .btn-primary:active {
            transform: translateY(-1px);
        }

        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-group i {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            color: #1a5c3a;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            z-index: 2;
        }

        .form-control:focus ~ i {
            color: #0a3d62;
            transform: translateY(-50%) scale(1.1);
        }

        .footer-text {
            color: #000000;
            text-align: center;
            font-size: 0.8rem;
            opacity: 0.85;
            margin-top: 1.5rem;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            font-weight: 500;
        }

        .login-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }

        form {
            width: 100%;
        }

        /* Responsive Design */
        @media (max-width: 576px) {
            .login-container {
                padding: 2rem 1.5rem;
                max-width: 95%;
                border-radius: 15px;
            }
            .login-container::before {
                border-radius: 17px;
            }
            .madrassa-name {
                font-size: 1.4rem;
            }
            .madrassa-address {
                font-size: 0.75rem;
            }
            .logo {
                max-width: 75px;
            }
            .btn-primary {
                font-size: 0.9rem;
                padding: 0.75rem;
            }
            .form-control {
                font-size: 0.9rem;
                padding: 0.75rem 0.75rem 0.75rem 2.75rem;
            }
            .form-group i {
                font-size: 1rem;
                left: 12px;
            }
            .error {
                font-size: 0.85rem;
                padding: 0.7rem;
            }
            .footer-text {
                font-size: 0.75rem;
            }
            .bismillah {
                font-size: 1.2rem;
            }
            .lantern-glow {
                right: 5%;
                width: 150px;
                height: 150px;
            }
        }
    </style>
</head>
<body>
    <!-- Background Image -->
    <div class="bg-image"></div>
    
    <!-- Dark Overlay for readability -->
    <div class="bg-overlay"></div>
    
    <!-- Floating Particles -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    
    <!-- Lantern Glow Effect -->
    <div class="lantern-glow"></div>
    
    <div class="login-container">
        <div class="bismillah">بِسْمِ اللَّهِ الرَّحْمَنِ الرَّحِيم</div>
        <img src="logo.jpg" alt="Madrassa Al-Farooqia Logo" class="logo">
        <h3 class="madrassa-name">المدرسہ الفاروقیہ للتجوید والقراءت</h3>
        <p class="madrassa-address">نیو کالونی مٹہ سوات الباکستان</p>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($_SESSION['error']); ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="login-wrapper">
            <form method="POST" action="login.php" autocomplete="off">
                <div class="form-group">
                    <i class="fas fa-user"></i>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Enter username" required autocomplete="off">
                </div>

                <div class="form-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter password" required autocomplete="off">
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Login
                    </button>
                </div>
            </form>
            <div class="footer-text" >Powered by Softrayz IT Solutions | 03452134977</div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Add subtle parallax effect on mouse move
        document.addEventListener('mousemove', function(e) {
            const bgImage = document.querySelector('.bg-image');
            const mouseX = (e.clientX / window.innerWidth - 0.5) * 10;
            const mouseY = (e.clientY / window.innerHeight - 0.5) * 10;
            
            bgImage.style.transform = `translate(${mouseX}px, ${mouseY}px) scale(1.1)`;
        });

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>