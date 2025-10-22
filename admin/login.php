
<?php
require_once '../app/core/db.php';

// Check if already logged in as admin (unchanged)
if (is_admin_logged_in()) {
    header('Location: dashboard.php');
    exit();
}

// Handle login form submission (unchanged)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['username'];
                $_SESSION['admin_last_activity'] = time();

                $stmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, action, timestamp) VALUES (?, 'login', NOW())");
                $stmt->execute([$admin['id']]);

                header('Location: dashboard.php');
                exit();
            } else {
                $error = "Invalid username or password";
            }
        } catch(PDOException $e) {
            $error = "Login failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Wigtopia Admin - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        @keyframes gradient-shift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
        @keyframes glow {
            0%, 100% { box-shadow: 0 0 15px rgba(251, 191, 36, 0.4); }
            50% { box-shadow: 0 0 25px rgba(251, 191, 36, 0.7); }
        }
        .gradient-bg {
            background: linear-gradient(-45deg, #92400e, #d97706, #f59e0b, #fbbf24, #f59e0b);
            background-size: 400% 400%;
            animation: gradient-shift 15s ease infinite;
        }
        .float-animation {
            animation: float 6s ease-in-out infinite;
        }
        .glow-animation {
            animation: glow 2s ease-in-out infinite alternate;
        }
        /* Mobile-specific adjustments */
        @media (max-width: 640px) {
            .float-animation {
                animation: none; /* Disable float animation on mobile for simplicity */
            }
            .glow-animation {
                box-shadow: 0 0 10px rgba(251, 191, 36, 0.4); /* Softer shadow on mobile */
            }
            input, button {
                touch-action: manipulation; /* Improve touch responsiveness */
            }
        }
    </style>
</head>
<body class="min-h-screen gradient-bg flex items-center justify-center p-4 sm:p-6">
    <div class="w-full max-w-sm sm:max-w-md">
        <!-- Header -->
        <div class="text-center mb-6">
            <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2 float-animation">Wigtopia Admin</h1>
            <p class="text-amber-100 text-sm">Administrative Control Panel</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white/10 backdrop-blur-lg rounded-2xl sm:rounded-3xl shadow-2xl border border-white/20 p-6 sm:p-8 glow-animation">
            <form method="POST" class="space-y-4">
                <?php if (isset($error)): ?>
                    <div class="bg-red-500/20 border border-red-500/50 text-red-100 px-4 py-2 rounded-lg text-center text-sm">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <div>
                    <label for="username" class="block text-xs sm:text-sm font-medium text-amber-100 mb-1">Username</label>
                    <input type="text" id="username" name="username"
                           class="w-full px-3 py-2 sm:px-4 sm:py-3 bg-white/20 border border-white/30 rounded-lg text-white placeholder-amber-200 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent backdrop-blur-sm text-sm sm:text-base"
                           placeholder="Enter your username" required>
                </div>

                <div>
                    <label for="password" class="block text-xs sm:text-sm font-medium text-amber-100 mb-1">Password</label>
                    <div class="relative">
                        <input type="password" id="password" name="password"
                               class="w-full px-3 py-2 sm:px-4 sm:py-3 pr-10 sm:pr-12 bg-white/20 border border-white/30 rounded-lg text-white placeholder-amber-200 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent backdrop-blur-sm text-sm sm:text-base"
                               placeholder="Enter your password" required>
                        <button type="button" id="togglePassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-amber-200 hover:text-white transition-colors duration-200 focus:outline-none">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 sm:py-3 px-4 sm:px-6 rounded-lg transition-all duration-300 transform hover:scale-105 hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:ring-offset-transparent text-sm sm:text-base">
                    Sign In to Admin Panel
                </button>
            </form>

            <div class="mt-4 text-center">
                <a href="password-recovery.php" class="text-amber-200 hover:text-white transition-colors duration-300 text-xs sm:text-sm">
                    Forgot your password?
                </a>
            </div>

            <div class="mt-2 text-center">
                <a href="../index.php" class="text-amber-200 hover:text-white transition-colors duration-300 text-xs sm:text-sm">
                    ← Back to Wigtopia Website
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6">
            <p class="text-amber-200/80 text-xs sm:text-sm">© 2025 Wigtopia Admin Panel. All rights reserved.</p>
        </div>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eyeIcon');

        togglePassword.addEventListener('click', function() {
            // Toggle the type attribute
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle the eye icon
            if (type === 'password') {
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
            } else {
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
            }
        });
    </script>
</body>
</html>