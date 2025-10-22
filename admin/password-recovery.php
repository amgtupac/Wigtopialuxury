<?php
require_once '../app/core/db.php';

// Check if already logged in as admin
if (is_admin_logged_in()) {
    header('Location: dashboard.php');
    exit();
}

// Handle password recovery form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitize_input($_POST['username']);
    $recovery_method = $_POST['recovery_method'];

    if (empty($username)) {
        $error = "Please enter your username";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if (!$admin) {
                $error = "No admin account found with that username";
            } else {
                if ($recovery_method === 'security_question') {
                    // For now, we'll use a simple email-based recovery
                    // In a production system, you'd implement proper security questions
                    $error = "Security question recovery not implemented yet. Please contact the system administrator.";
                } elseif ($recovery_method === 'email') {
                    // Generate a secure token for password reset
                    $token = bin2hex(random_bytes(32));
                    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    // Store reset token in database (you'd need to add a password_reset_tokens table)
                    $stmt = $pdo->prepare("INSERT INTO admin_password_resets (admin_id, token, expiry) VALUES (?, ?, ?)");
                    $stmt->execute([$admin['id'], $token, $expiry]);

                    // Send email with reset link (you'd need to implement actual email sending)
                    $reset_link = "http://" . $_SERVER['HTTP_HOST'] . "/admin/reset-password.php?token=" . $token;

                    // For demo purposes, we'll just show the reset link
                    $success = "Password reset link generated: <a href='" . $reset_link . "' class='text-blue-600 hover:underline'>" . $reset_link . "</a>";
                    $success .= "<br><br><small>This is a demo - in production, this link would be emailed to the admin.</small>";
                }
            }
        } catch(PDOException $e) {
            $error = "Recovery failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Wigtopia Admin - Password Recovery</title>
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
        @media (max-width: 640px) {
            .float-animation {
                animation: none;
            }
            .glow-animation {
                box-shadow: 0 0 10px rgba(251, 191, 36, 0.4);
            }
            input, button, select {
                touch-action: manipulation;
            }
        }
    </style>
</head>
<body class="min-h-screen gradient-bg flex items-center justify-center p-4 sm:p-6">
    <div class="w-full max-w-md">
        <!-- Header -->
        <div class="text-center mb-6">
            <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2 float-animation">Admin Password Recovery</h1>
            <p class="text-amber-100 text-sm">Recover your admin account access</p>
        </div>

        <!-- Recovery Card -->
        <div class="bg-white/10 backdrop-blur-lg rounded-2xl sm:rounded-3xl shadow-2xl border border-white/20 p-6 sm:p-8 glow-animation">
            <?php if (isset($error)): ?>
                <div class="bg-red-500/20 border border-red-500/50 text-red-100 px-4 py-2 rounded-lg mb-4 text-center text-sm">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="bg-green-500/20 border border-green-500/50 text-green-100 px-4 py-2 rounded-lg mb-4 text-center text-sm">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-4">
                <div>
                    <label for="username" class="block text-xs sm:text-sm font-medium text-amber-100 mb-1">Username</label>
                    <input type="text" id="username" name="username"
                           class="w-full px-3 py-2 sm:px-4 sm:py-3 bg-white/20 border border-white/30 rounded-lg text-white placeholder-amber-200 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent backdrop-blur-sm text-sm sm:text-base"
                           placeholder="Enter your admin username" required>
                </div>

                <div>
                    <label for="recovery_method" class="block text-xs sm:text-sm font-medium text-amber-100 mb-1">Recovery Method</label>
                    <select id="recovery_method" name="recovery_method"
                            class="w-full px-3 py-2 sm:px-4 sm:py-3 bg-white/20 border border-white/30 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent backdrop-blur-sm text-sm sm:text-base">
                        <option value="email">Email Recovery</option>
                        <option value="security_question">Security Question</option>
                    </select>
                </div>

                <button type="submit"
                        class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 sm:py-3 px-4 sm:px-6 rounded-lg transition-all duration-300 transform hover:scale-105 hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:ring-offset-transparent text-sm sm:text-base">
                    Send Recovery Instructions
                </button>
            </form>

            <div class="mt-4 text-center">
                <a href="login.php" class="text-amber-200 hover:text-white transition-colors duration-300 text-xs sm:text-sm">
                    ← Back to Login
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-6">
            <p class="text-amber-200/80 text-xs sm:text-sm">© 2025 Wigtopia Admin Panel. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
