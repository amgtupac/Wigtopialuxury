<?php
require_once '../app/core/db.php';

// Check if already logged in as admin
if (is_admin_logged_in()) {
    header('Location: dashboard.php');
    exit();
}

// Check if token is provided
if (!isset($_GET['token'])) {
    header('Location: password-recovery.php');
    exit();
}

$token = $_GET['token'];

// Verify token
try {
    $stmt = $pdo->prepare("SELECT * FROM admin_password_resets WHERE token = ? AND expiry > NOW()");
    $stmt->execute([$token]);
    $reset_request = $stmt->fetch();

    if (!$reset_request) {
        $error = "Invalid or expired reset token";
    }
} catch(PDOException $e) {
    $error = "Error verifying reset token: " . $e->getMessage();
}

// Handle password reset form
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($reset_request)) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Both password fields are required";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        try {
            // Update admin password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $reset_request['admin_id']]);

            // Delete the reset token
            $stmt = $pdo->prepare("DELETE FROM admin_password_resets WHERE token = ?");
            $stmt->execute([$token]);

            // Log the password reset
            $stmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, 'password_reset', 'Password reset via recovery link')");
            $stmt->execute([$reset_request['admin_id']]);

            $success = "Password reset successfully! You can now login with your new password.";
        } catch(PDOException $e) {
            $error = "Error resetting password: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Wigtopia Admin - Reset Password</title>
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
            input, button {
                touch-action: manipulation;
            }
        }
    </style>
</head>
<body class="min-h-screen gradient-bg flex items-center justify-center p-4 sm:p-6">
    <div class="w-full max-w-md">
        <!-- Header -->
        <div class="text-center mb-6">
            <h1 class="text-3xl sm:text-4xl font-bold text-white mb-2 float-animation">Reset Admin Password</h1>
            <p class="text-amber-100 text-sm">Enter your new password</p>
        </div>

        <!-- Reset Card -->
        <div class="bg-white/10 backdrop-blur-lg rounded-2xl sm:rounded-3xl shadow-2xl border border-white/20 p-6 sm:p-8 glow-animation">
            <?php if (isset($error)): ?>
                <div class="bg-red-500/20 border border-red-500/50 text-red-100 px-4 py-2 rounded-lg mb-4 text-center text-sm">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="bg-green-500/20 border border-green-500/50 text-green-100 px-4 py-2 rounded-lg mb-4 text-center text-sm">
                    <?php echo htmlspecialchars($success); ?>
                    <br><br>
                    <a href="login.php" class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition-colors inline-block mt-2">
                        Go to Login
                    </a>
                </div>
            <?php endif; ?>

            <?php if (!isset($success) && isset($reset_request)): ?>
                <form method="POST" class="space-y-4">
                    <div>
                        <label for="new_password" class="block text-xs sm:text-sm font-medium text-amber-100 mb-1">New Password</label>
                        <input type="password" id="new_password" name="new_password"
                               class="w-full px-3 py-2 sm:px-4 sm:py-3 bg-white/20 border border-white/30 rounded-lg text-white placeholder-amber-200 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent backdrop-blur-sm text-sm sm:text-base"
                               placeholder="Enter your new password" required>
                        <p class="text-xs text-amber-200 mt-1">Minimum 8 characters</p>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-xs sm:text-sm font-medium text-amber-100 mb-1">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                               class="w-full px-3 py-2 sm:px-4 sm:py-3 bg-white/20 border border-white/30 rounded-lg text-white placeholder-amber-200 focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent backdrop-blur-sm text-sm sm:text-base"
                               placeholder="Confirm your new password" required>
                    </div>

                    <button type="submit"
                            class="w-full bg-amber-600 hover:bg-amber-700 text-white font-semibold py-2 sm:py-3 px-4 sm:px-6 rounded-lg transition-all duration-300 transform hover:scale-105 hover:shadow-xl focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-2 focus:ring-offset-transparent text-sm sm:text-base">
                        Reset Password
                    </button>
                </form>
            <?php endif; ?>

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
