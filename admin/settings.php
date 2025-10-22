<?php
require_once '../app/core/db.php';
require_admin_login();

// Get current admin info
try {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if (!$admin) {
        redirect('logout.php', 'Admin account not found');
    }
} catch(PDOException $e) {
    die("Error loading admin data: " . $e->getMessage());
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $password_error = "All password fields are required";
    } elseif ($new_password !== $confirm_password) {
        $password_error = "New passwords do not match";
    } elseif (strlen($new_password) < 8) {
        $password_error = "Password must be at least 8 characters long";
    } elseif (!password_verify($current_password, $admin['password'])) {
        $password_error = "Current password is incorrect";
    } else {
        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $_SESSION['admin_id']]);

            // Log the password change
            $stmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, 'password_change', 'Password changed successfully')");
            $stmt->execute([$_SESSION['admin_id']]);

            $password_success = "Password changed successfully";
        } catch(PDOException $e) {
            $password_error = "Error updating password: " . $e->getMessage();
        }
    }
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $username = sanitize_input($_POST['username']);

    if (empty($username)) {
        $profile_error = "Username is required";
    } else {
        try {
            // Check if username is already taken by another admin
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
            $stmt->execute([$username, $_SESSION['admin_id']]);
            if ($stmt->fetch()) {
                $profile_error = "Username already exists";
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET username = ? WHERE id = ?");
                $stmt->execute([$username, $_SESSION['admin_id']]);

                $_SESSION['admin_name'] = $username;

                // Log the profile update
                $details = 'Username changed to: ' . $username;
                $stmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, 'profile_update', ?)");
                $stmt->execute([$_SESSION['admin_id'], $details]);

                $profile_success = "Profile updated successfully";
            }
        } catch(PDOException $e) {
            $profile_error = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Get recent admin activity
try {
    $stmt = $pdo->prepare("
        SELECT a.*, ad.username
        FROM admin_activity_log a
        LEFT JOIN admins ad ON a.admin_id = ad.id
        ORDER BY a.timestamp DESC
        LIMIT 20
    ");
    $stmt->execute();
    $admin_activity = $stmt->fetchAll();
} catch(PDOException $e) {
    $admin_activity = [];
}

// Get system statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total_admins FROM admins");
    $admin_count = $stmt->fetch()['total_admins'];

    $stmt = $pdo->query("SELECT COUNT(*) as total_activity FROM admin_activity_log WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $recent_activity = $stmt->fetch()['total_activity'];
} catch(PDOException $e) {
    $admin_count = 0;
    $recent_activity = 0;
}

// Get payment information
try {
    $stmt = $pdo->query("
        SELECT pi.*, 
               u.name as user_name, 
               u.email as user_email,
               o.id as order_number,
               o.total as order_total
        FROM payment_info pi
        LEFT JOIN users u ON pi.user_id = u.id
        LEFT JOIN orders o ON pi.order_id = o.id
        ORDER BY pi.created_at DESC
        LIMIT 50
    ");
    $payment_records = $stmt->fetchAll();
} catch(PDOException $e) {
    $payment_records = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wigtopia Admin - Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../public/assets/css/smooth-animations.css" rel="stylesheet">
    <style>
        .sidebar {
            background: linear-gradient(180deg, #92400e, #78350f);
            transition: transform 0.3s ease-in-out;
        }
        .sidebar-hidden {
            transform: translateX(-100%);
        }
        .slide-in-up {
            animation: slideInUp 0.6s ease-out forwards;
        }
        .fade-in {
            animation: fadeIn 0.8s ease-out forwards;
        }
        @keyframes slideInUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }
        .tab-active {
            background-color: #f59e0b;
            color: white;
        }
        .tab-inactive {
            background-color: transparent;
            color: #d97706;
            border: 1px solid #d97706;
        }
        .tab-inactive:hover {
            background-color: #f59e0b;
            color: white;
        }
        /* Mobile responsive adjustments */
        @media (max-width: 640px) {
            .tab-button {
                touch-action: manipulation;
            }
            input, button, select {
                touch-action: manipulation;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Sidebar -->
    <div id="sidebar" class="fixed left-0 top-0 h-full w-64 sidebar shadow-2xl z-50 md:translate-x-0 sidebar-hidden">
        <div class="p-4">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 bg-amber-400 rounded-full flex items-center justify-center">
                        <i class="fas fa-crown text-amber-900"></i>
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-white">Wigtopia</h2>
                        <p class="text-amber-200 text-sm">Admin Panel</p>
                    </div>
                </div>
                <button id="closeSidebar" class="md:hidden text-white">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>

            <nav class="space-y-2">
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 text-amber-200 hover:bg-amber-700 hover:text-white rounded-xl transition-all duration-300">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="users.php" class="flex items-center space-x-3 px-4 py-3 text-amber-200 hover:bg-amber-700 hover:text-white rounded-xl transition-all duration-300">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                <a href="orders.php" class="flex items-center space-x-3 px-4 py-3 text-amber-200 hover:bg-amber-700 hover:text-white rounded-xl transition-all duration-300">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                </a>
                <a href="products.php" class="flex items-center space-x-3 px-4 py-3 text-amber-200 hover:bg-amber-700 hover:text-white rounded-xl transition-all duration-300">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
                <a href="categories.php" class="flex items-center space-x-3 px-4 py-3 text-amber-200 hover:bg-amber-700 hover:text-white rounded-xl transition-all duration-300">
                    <i class="fas fa-tags"></i>
                    <span>Categories</span>
                </a>
                <a href="analytics.php" class="flex items-center space-x-3 px-4 py-3 text-amber-200 hover:bg-amber-700 hover:text-white rounded-xl transition-all duration-300">
                    <i class="fas fa-chart-bar"></i>
                    <span>Analytics</span>
                </a>
                <a href="settings.php" class="flex items-center space-x-3 px-4 py-3 bg-amber-600 text-white rounded-xl">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </nav>

            <div class="mt-8 pt-8 border-t border-amber-700">
                <a href="../index.php" class="flex items-center space-x-3 px-4 py-3 text-amber-200 hover:bg-amber-700 hover:text-white rounded-xl transition-all duration-300 w-full">
                    <i class="fas fa-external-link-alt"></i>
                    <span>Visit Website</span>
                </a>
                <a href="logout.php" class="flex items-center space-x-3 px-4 py-3 text-red-300 hover:bg-red-700 hover:text-white rounded-xl transition-all duration-300">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div id="mainContent" class="md:ml-64 transition-all duration-300">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b sticky top-0 z-40">
            <div class="px-4 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <button id="toggleSidebar" class="md:hidden text-gray-900">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <div>
                            <h1 class="text-xl font-bold text-gray-900">Admin Settings</h1>
                            <p class="text-gray-600 text-sm">Manage your account and system preferences</p>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Settings Content -->
        <main class="p-6">
            <!-- Settings Tabs -->
            <div class="mb-8">
                <nav class="flex flex-wrap gap-2 bg-white/50 backdrop-blur-sm rounded-2xl p-1 border border-white/20">
                    <button onclick="showTab('profile')" class="tab-button tab-active px-6 py-3 rounded-xl font-semibold text-sm transition-all duration-300 flex items-center space-x-2">
                        <i class="fas fa-user text-sm"></i>
                        <span>Profile</span>
                    </button>
                    <button onclick="showTab('security')" class="tab-button tab-inactive px-6 py-3 rounded-xl font-semibold text-sm transition-all duration-300 flex items-center space-x-2">
                        <i class="fas fa-lock text-sm"></i>
                        <span>Security</span>
                    </button>
                    <button onclick="showTab('activity')" class="tab-button tab-inactive px-6 py-3 rounded-xl font-semibold text-sm transition-all duration-300 flex items-center space-x-2">
                        <i class="fas fa-history text-sm"></i>
                        <span>Activity Log</span>
                    </button>
                    <button onclick="showTab('system')" class="tab-button tab-inactive px-6 py-3 rounded-xl font-semibold text-sm transition-all duration-300 flex items-center space-x-2">
                        <i class="fas fa-server text-sm"></i>
                        <span>System</span>
                    </button>
                    <button onclick="showTab('payments')" class="tab-button tab-inactive px-6 py-3 rounded-xl font-semibold text-sm transition-all duration-300 flex items-center space-x-2">
                        <i class="fas fa-credit-card text-sm"></i>
                        <span>Payments</span>
                    </button>
                </nav>
            </div>

            <!-- Profile Tab -->
            <div id="profile-tab" class="tab-content">
                <div class="bg-white/60 backdrop-blur-sm rounded-2xl card-shadow p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-slate-900 flex items-center space-x-3">
                            <i class="fas fa-user-circle text-2xl text-slate-400"></i>
                            <span>Admin Profile</span>
                        </h3>
                    </div>

                    <?php if (isset($profile_success)): ?>
                        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl mb-6 flex items-center space-x-2">
                            <i class="fas fa-check-circle text-green-500"></i>
                            <span><?php echo htmlspecialchars($profile_success); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($profile_error)): ?>
                        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl mb-6 flex items-center space-x-2">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                            <span><?php echo htmlspecialchars($profile_error); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label for="username" class="block text-sm font-semibold text-slate-700 mb-2">Username</label>
                                <input type="text" id="username" name="username"
                                       value="<?php echo htmlspecialchars($admin['username']); ?>"
                                       class="w-full px-4 py-3 border border-slate-300 rounded-xl input-focus bg-white/50 backdrop-blur-sm"
                                       required>
                            </div>
                            <div>
                                <label class="block text-sm font-semibold text-slate-700 mb-2">Account Created</label>
                                <p class="text-slate-600 px-4 py-3 bg-white/50 rounded-xl"><?php echo date('F j, Y \a\t g:i A', strtotime($admin['created_at'])); ?></p>
                            </div>
                        </div>

                        <button type="submit" name="update_profile" class="btn-primary text-white px-8 py-3 rounded-xl font-semibold flex items-center space-x-2 w-full md:w-auto justify-center">
                            <i class="fas fa-save"></i>
                            <span>Update Profile</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Security Tab -->
            <div id="security-tab" class="tab-content" style="display: none;">
                <div class="bg-white/60 backdrop-blur-sm rounded-2xl card-shadow p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-slate-900 flex items-center space-x-3">
                            <i class="fas fa-shield-alt text-2xl text-slate-400"></i>
                            <span>Password & Security</span>
                        </h3>
                    </div>

                    <?php if (isset($password_success)): ?>
                        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-xl mb-6 flex items-center space-x-2">
                            <i class="fas fa-check-circle text-green-500"></i>
                            <span><?php echo htmlspecialchars($password_success); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($password_error)): ?>
                        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl mb-6 flex items-center space-x-2">
                            <i class="fas fa-exclamation-triangle text-red-500"></i>
                            <span><?php echo htmlspecialchars($password_error); ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <div>
                            <label for="current_password" class="block text-sm font-semibold text-slate-700 mb-2">Current Password</label>
                            <div class="relative">
                                <input type="password" id="current_password" name="current_password"
                                       class="w-full px-4 py-3 pr-12 border border-slate-300 rounded-xl input-focus bg-white/50 backdrop-blur-sm"
                                       required>
                                <button type="button" onclick="togglePassword('current_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-eye" id="current_password_icon"></i>
                                </button>
                            </div>
                        </div>

                        <div>
                            <label for="new_password" class="block text-sm font-semibold text-slate-700 mb-2">New Password</label>
                            <div class="relative">
                                <input type="password" id="new_password" name="new_password"
                                       class="w-full px-4 py-3 pr-12 border border-slate-300 rounded-xl input-focus bg-white/50 backdrop-blur-sm"
                                       required>
                                <button type="button" onclick="togglePassword('new_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-eye" id="new_password_icon"></i>
                                </button>
                            </div>
                            <p class="text-xs text-slate-500 mt-2">Must be at least 8 characters long</p>
                        </div>

                        <div>
                            <label for="confirm_password" class="block text-sm font-semibold text-slate-700 mb-2">Confirm New Password</label>
                            <div class="relative">
                                <input type="password" id="confirm_password" name="confirm_password"
                                       class="w-full px-4 py-3 pr-12 border border-slate-300 rounded-xl input-focus bg-white/50 backdrop-blur-sm"
                                       required>
                                <button type="button" onclick="togglePassword('confirm_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700">
                                    <i class="fas fa-eye" id="confirm_password_icon"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" name="change_password" class="btn-primary text-white px-8 py-3 rounded-xl font-semibold flex items-center space-x-2 w-full md:w-auto justify-center">
                            <i class="fas fa-key"></i>
                            <span>Change Password</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Activity Log Tab -->
            <div id="activity-tab" class="tab-content" style="display: none;">
                <div class="bg-white/60 backdrop-blur-sm rounded-2xl card-shadow p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-slate-900 flex items-center space-x-3">
                            <i class="fas fa-history text-2xl text-slate-400"></i>
                            <span>Recent Admin Activity</span>
                        </h3>
                    </div>

                    <?php if (empty($admin_activity)): ?>
                        <div class="text-center py-12">
                            <i class="fas fa-clock text-6xl text-slate-300 mb-4"></i>
                            <p class="text-slate-500 text-lg">No recent activity found.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($admin_activity as $activity): ?>
                                <div class="flex items-center justify-between py-4 px-6 bg-white/30 rounded-xl border border-white/20">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-10 h-10 bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl flex items-center justify-center">
                                            <i class="fas fa-user text-white text-sm"></i>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">
                                                <?php echo htmlspecialchars($activity['username'] ?? 'Unknown Admin'); ?>
                                            </p>
                                            <p class="text-xs text-slate-500 capitalize">
                                                <?php echo htmlspecialchars($activity['action']); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-slate-500 font-medium">
                                            <?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?>
                                        </p>
                                        <?php if (!empty($activity['details'])): ?>
                                            <p class="text-xs text-slate-400 mt-1 italic">
                                                <?php echo htmlspecialchars($activity['details']); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Tab -->
            <div id="system-tab" class="tab-content" style="display: none;">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- System Statistics -->
                    <div class="bg-white/60 backdrop-blur-sm rounded-2xl card-shadow p-8">
                        <h3 class="text-xl font-bold text-slate-900 mb-6 flex items-center space-x-3">
                            <i class="fas fa-chart-pie text-2xl text-slate-400"></i>
                            <span>System Overview</span>
                        </h3>

                        <div class="space-y-6">
                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl">
                                <div>
                                    <p class="text-sm font-medium text-slate-600">Total Admins</p>
                                    <p class="text-3xl font-bold text-slate-900"><?php echo $admin_count; ?></p>
                                </div>
                                <i class="fas fa-users text-blue-500 text-4xl"></i>
                            </div>
                            <div class="flex items-center justify-between p-4 bg-gradient-to-r from-green-50 to-emerald-50 rounded-xl">
                                <div>
                                    <p class="text-sm font-medium text-slate-600">Recent Activity (24h)</p>
                                    <p class="text-3xl font-bold text-slate-900"><?php echo $recent_activity; ?></p>
                                </div>
                                <i class="fas fa-history text-green-500 text-4xl"></i>
                            </div>
                        </div>
                    </div>

                    <!-- System Actions -->
                    <div class="bg-white/60 backdrop-blur-sm rounded-2xl card-shadow p-8">
                        <h3 class="text-xl font-bold text-slate-900 mb-6 flex items-center space-x-3">
                            <i class="fas fa-tools text-2xl text-slate-400"></i>
                            <span>System Actions</span>
                        </h3>

                        <div class="space-y-3">
                            <button onclick="clearActivityLog()" class="w-full bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700 transition-colors font-semibold flex items-center justify-center space-x-2">
                                <i class="fas fa-trash"></i>
                                <span>Clear Activity Log</span>
                            </button>

                            <button onclick="exportSystemData()" class="w-full bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors font-semibold flex items-center justify-center space-x-2">
                                <i class="fas fa-download"></i>
                                <span>Export System Data</span>
                            </button>

                            <button onclick="systemMaintenance()" class="w-full bg-amber-600 text-white px-6 py-3 rounded-lg hover:bg-amber-700 transition-colors font-semibold flex items-center justify-center space-x-2">
                                <i class="fas fa-wrench"></i>
                                <span>System Maintenance</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Database Info -->
                <div class="bg-white/60 backdrop-blur-sm rounded-2xl card-shadow p-8">
                    <h3 class="text-xl font-bold text-slate-900 mb-6 flex items-center space-x-3">
                        <i class="fas fa-database text-2xl text-slate-400"></i>
                        <span>Database Information</span>
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="text-center p-6 bg-gradient-to-b from-slate-50 to-slate-100 rounded-xl border border-slate-200">
                            <div class="text-3xl font-bold text-slate-900 mb-2" id="dbSize">-</div>
                            <div class="text-sm text-slate-600">Database Size</div>
                        </div>
                        <div class="text-center p-6 bg-gradient-to-b from-slate-50 to-slate-100 rounded-xl border border-slate-200">
                            <div class="text-3xl font-bold text-slate-900 mb-2" id="totalTables">-</div>
                            <div class="text-sm text-slate-600">Tables</div>
                        </div>
                        <div class="text-center p-6 bg-gradient-to-b from-slate-50 to-slate-100 rounded-xl border border-slate-200">
                            <div class="text-3xl font-bold text-slate-900 mb-2" id="lastBackup">-</div>
                            <div class="text-sm text-slate-600">Last Backup</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Info Tab -->
            <div id="payments-tab" class="tab-content" style="display: none;">
                <div class="bg-white/60 backdrop-blur-sm rounded-2xl card-shadow p-8">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-slate-900 flex items-center space-x-3">
                            <i class="fas fa-credit-card text-2xl text-slate-400"></i>
                            <span>Payment Information Records</span>
                        </h3>
                        <span class="text-sm text-slate-600 bg-slate-100 px-3 py-1 rounded-full"><?php echo count($payment_records); ?> records</span>
                    </div>

                    <?php if (empty($payment_records)): ?>
                        <div class="text-center py-16">
                            <i class="fas fa-credit-card text-6xl text-slate-300 mb-4"></i>
                            <p class="text-slate-500 text-lg mb-2">No payment information found.</p>
                            <p class="text-sm text-slate-400">Payment records will appear here when customers make purchases.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="w-full">
                                <thead class="bg-gradient-to-r from-slate-50 to-slate-100">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Customer</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Order #</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Card (Last 4)</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Expiry</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Method</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-4 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    <?php foreach ($payment_records as $payment): ?>
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-slate-900">
                                                #<?php echo htmlspecialchars($payment['id']); ?>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="text-sm font-semibold text-slate-900">
                                                    <?php echo htmlspecialchars($payment['user_name'] ?? 'Guest'); ?>
                                                </div>
                                                <div class="text-xs text-slate-500">
                                                    <?php echo htmlspecialchars($payment['user_email'] ?? 'N/A'); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($payment['order_number']): ?>
                                                    <a href="orders.php?id=<?php echo $payment['order_number']; ?>" class="text-blue-600 hover:text-blue-700 font-medium text-sm">
                                                        #<?php echo htmlspecialchars($payment['order_number']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-slate-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center text-sm text-slate-900">
                                                    <i class="far fa-credit-card text-slate-400 mr-2"></i>
                                                    ****<?php echo htmlspecialchars($payment['card_number_last4']); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-900">
                                                <?php echo htmlspecialchars($payment['expiry_date']); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-700">
                                                    <?php echo ucfirst(htmlspecialchars($payment['payment_method'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-green-600">
                                                <?php if ($payment['order_total']): ?>
                                                    $<?php echo number_format($payment['order_total'], 2); ?>
                                                <?php else: ?>
                                                    <span class="text-slate-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500">
                                                <?php echo date('M j, Y', strtotime($payment['created_at'])); ?>
                                                <div class="text-xs text-slate-400">
                                                    <?php echo date('g:i A', strtotime($payment['created_at'])); ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Export Button -->
                        <div class="mt-6 flex justify-end">
                            <button onclick="exportPaymentData()" class="bg-green-600 text-white px-6 py-3 rounded-lg hover:bg-green-700 transition-colors font-semibold flex items-center space-x-2">
                                <i class="fas fa-download"></i>
                                <span>Export Payment Data</span>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        const toggleSidebar = document.getElementById('toggleSidebar');
        const closeSidebar = document.getElementById('closeSidebar');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        toggleSidebar.addEventListener('click', () => {
            sidebar.classList.toggle('sidebar-hidden');
            if (!sidebar.classList.contains('sidebar-hidden')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = 'auto';
            }
        });

        closeSidebar.addEventListener('click', () => {
            sidebar.classList.add('sidebar-hidden');
            document.body.style.overflow = 'auto';
        });

        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.style.display = 'none';
            });

            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-button');
            tabButtons.forEach(button => {
                button.classList.remove('tab-active');
                button.classList.add('tab-inactive');
            });

            // Show selected tab content
            document.getElementById(tabName + '-tab').style.display = 'block';

            // Add active class to clicked button
            event.target.classList.remove('tab-inactive');
            event.target.classList.add('tab-active');
        }

        function clearActivityLog() {
            if (confirm('Are you sure you want to clear all activity logs? This action cannot be undone.')) {
                fetch('clear-activity-log.php', { method: 'POST' })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error clearing activity log');
                        }
                    });
            }
        }

        function exportSystemData() {
            alert('System data export feature would be implemented here');
        }

        function systemMaintenance() {
            alert('System maintenance feature would be implemented here');
        }

        function exportPaymentData() {
            alert('Payment data export feature would be implemented here');
        }

        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + '_icon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordField.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Load system info
        fetch('get-system-info.php')
            .then(response => response.json())
            .then(data => {
                if (data.dbSize) document.getElementById('dbSize').textContent = data.dbSize;
                if (data.totalTables) document.getElementById('totalTables').textContent = data.totalTables;
                if (data.lastBackup) document.getElementById('lastBackup').textContent = data.lastBackup;
            });
    </script>
    <script src="../public/assets/js/smooth-loader.js"></script>
</body>
</html>