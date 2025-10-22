<?php
require_once '../app/core/db.php';

// Redirect if not logged in
if (!is_logged_in()) {
    redirect('login.php', 'Please login to access your account');
}

// Get user information
$user_id = $_SESSION['user_id'];
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) {
        redirect('login.php', 'User not found');
    }
} catch (PDOException $e) {
    redirect('index.php', 'Error loading user information');
}

// Handle form submissions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = "Please fill in all password fields";
            } elseif ($new_password !== $confirm_password) {
                $error = "New passwords do not match";
            } elseif (strlen($new_password) < 6) {
                $error = "Password must be at least 6 characters long";
            } elseif (!password_verify($current_password, $user['password'])) {
                $error = "Current password is incorrect";
            } else {
                try {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->execute([$hashed_password, $user_id]);
                    $message = "Password updated successfully!";
                } catch (PDOException $e) {
                    $error = "Error updating password";
                }
            }
            break;

        case 'update_profile':
            $name = sanitize_input($_POST['name'] ?? '');
            $email = sanitize_input($_POST['email'] ?? '');

            if (empty($name) || empty($email)) {
                $error = "Name and email are required";
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = "Please enter a valid email address";
            } else {
                try {
                    // Check if email is already taken by another user
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $stmt->execute([$email, $user_id]);
                    if ($stmt->fetch()) {
                        $error = "Email address is already in use";
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $user_id]);
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                        $message = "Profile updated successfully!";
                    }
                } catch (PDOException $e) {
                    $error = "Error updating profile";
                }
            }
            break;

        case 'delete_account':
            $confirm_text = $_POST['confirm_text'] ?? '';

            if ($confirm_text !== 'DELETE MY ACCOUNT') {
                $error = "Please type 'DELETE MY ACCOUNT' to confirm";
            } else {
                try {
                    // Delete user account
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);

                    // Clear session
                    session_destroy();

                    redirect('index.php', 'Your account has been deleted successfully');
                } catch (PDOException $e) {
                    $error = "Error deleting account";
                }
            }
            break;
    }
}

// Get user's order history
try {
    $stmt = $pdo->prepare("
        SELECT o.*, COUNT(oi.id) as item_count,
               SUM(oi.quantity * oi.price) as total_amount
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = [];
}

// Get user's total spending
try {
    $stmt = $pdo->prepare("
        SELECT SUM(total_amount) as total_spent,
               COUNT(*) as total_orders
        FROM orders
        WHERE user_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $spending_stats = $stmt->fetch();
} catch (PDOException $e) {
    $spending_stats = ['total_spent' => 0, 'total_orders' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Wigtopia</title>
    <meta name="description" content="Manage your Wigtopia account settings, view orders, and update your profile.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="assets/css/smooth-animations.css" rel="stylesheet">
    <style>
        .fade-in { animation: fadeIn 0.6s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .hover-scale { transition: transform 0.3s ease; }
        .hover-scale:hover { transform: scale(1.02); }
        .section-card { transition: box-shadow 0.3s ease; }
        .section-card:hover { box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>

    <main class="py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto">
            <!-- Page Header -->
            <div class="bg-gradient-to-r from-amber-50 to-amber-100 rounded-3xl p-6 sm:p-8 mb-8 fade-in">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-gray-800 mb-2">
                            Welcome back, <?php echo htmlspecialchars($user['name']); ?>! 👋
                        </h1>
                        <p class="text-gray-600">Manage your account settings and view your order history</p>
                    </div>
                    <div class="hidden sm:block">
                        <div class="bg-white/80 backdrop-blur-sm rounded-full px-6 py-3 shadow-lg">
                            <span class="text-amber-600 font-semibold">Member since <?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if ($message): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Sidebar - Account Overview -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-2xl shadow-lg p-6 section-card">
                        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            Account Overview
                        </h2>

                        <div class="space-y-4">
                            <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                                <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
                                    <span class="text-amber-600 font-semibold"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></span>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($user['name']); ?></p>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($user['email']); ?></p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-amber-600"><?php echo $spending_stats['total_orders']; ?></div>
                                    <div class="text-sm text-gray-600">Total Orders</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-2xl font-bold text-green-600">$<?php echo number_format($spending_stats['total_spent'], 2); ?></div>
                                    <div class="text-sm text-gray-600">Total Spent</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content - Account Settings -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- Profile Information -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 section-card">
                        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            Profile Information
                        </h2>

                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="update_profile">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent" required>
                            </div>

                            <button type="submit" class="bg-amber-600 text-white px-6 py-3 rounded-lg hover:bg-amber-700 transition-all duration-300 font-medium">
                                Update Profile
                            </button>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="bg-white rounded-2xl shadow-lg p-6 section-card">
                        <h2 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-2">
                            <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            Change Password
                        </h2>

                        <form method="POST" class="space-y-4">
                            <input type="hidden" name="action" value="change_password">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                <input type="password" name="current_password"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                <input type="password" name="new_password"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                <input type="password" name="confirm_password"
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent" required>
                            </div>

                            <button type="submit" class="bg-amber-600 text-white px-6 py-3 rounded-lg hover:bg-amber-700 transition-all duration-300 font-medium">
                                Change Password
                            </button>
                        </form>
                    </div>

                    <!-- Danger Zone -->
                    <div class="bg-red-50 border border-red-200 rounded-2xl p-6">
                        <h2 class="text-xl font-bold text-red-800 mb-6 flex items-center gap-2">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            Danger Zone
                        </h2>

                        <div class="mb-6">
                            <h3 class="text-lg font-semibold text-red-800 mb-2">Delete Account</h3>
                            <p class="text-red-600 text-sm mb-4">
                                Once you delete your account, there is no going back. This action cannot be undone.
                                All your data including orders, reviews, and account information will be permanently removed.
                            </p>

                            <form method="POST" class="space-y-4" onsubmit="return confirm('Are you absolutely sure you want to delete your account? This cannot be undone!')">
                                <input type="hidden" name="action" value="delete_account">

                                <div>
                                    <label class="block text-sm font-medium text-red-700 mb-2">
                                        Type "DELETE MY ACCOUNT" to confirm:
                                    </label>
                                    <input type="text" name="confirm_text"
                                           class="w-full px-4 py-3 border border-red-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                                           placeholder="DELETE MY ACCOUNT" required>
                                </div>

                                <button type="submit" class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition-all duration-300 font-medium">
                                    Delete My Account Permanently
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order History Section -->
            <div class="mt-12">
                <div class="bg-white rounded-2xl shadow-lg p-6 section-card">
                    <h2 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Recent Orders
                    </h2>
                    <a href="orders.php" class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition-all duration-300 font-medium mb-4 inline-block">
                        View All Orders
                    </a>

                    <?php if (empty($orders)): ?>
                        <div class="text-center py-12">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No orders yet</h3>
                            <p class="text-gray-500 mb-6">You haven't placed any orders yet. Start shopping to see your order history here!</p>
                            <a href="products.php" class="bg-amber-600 text-white px-6 py-3 rounded-lg hover:bg-amber-700 transition-all duration-300 font-medium inline-block">
                                Start Shopping
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order #</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($orders as $order): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                #<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M d, Y', strtotime($order['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo $order['item_count']; ?> item<?php echo $order['item_count'] !== 1 ? 's' : ''; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                $<?php echo number_format($order['total_amount'], 2); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs font-medium rounded-full
                                                    <?php echo $order['status'] === 'completed' ? 'bg-green-100 text-green-800' :
                                                              ($order['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' :
                                                              'bg-gray-100 text-gray-800'); ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="order-details.php?id=<?php echo $order['id']; ?>"
                                                   class="text-amber-600 hover:text-amber-900 transition-colors">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (count($orders) >= 10): ?>
                            <div class="text-center mt-6">
                                <a href="orders.php" class="text-amber-600 hover:text-amber-900 font-medium">
                                    View All Orders →
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        // Add some interactive enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide messages after 5 seconds
            setTimeout(() => {
                const messages = document.querySelectorAll('.bg-green-100, .bg-red-100');
                messages.forEach(msg => {
                    msg.style.transition = 'opacity 0.5s ease';
                    msg.style.opacity = '0';
                    setTimeout(() => msg.remove(), 500);
                });
            }, 5000);
        });
    </script>
    <script src="assets/js/smooth-loader.js"></script>
</body>
</html>
