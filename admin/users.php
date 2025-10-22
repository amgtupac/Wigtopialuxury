<?php
require_once '../app/core/db.php';
require_admin_login();

// Handle searchssssss and filter (unchanged)
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$filter = isset($_GET['filter']) ? sanitize_input($_GET['filter']) : 'all';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter !== 'all') {
    if ($filter === 'recent') {
        $where_conditions[] = "u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    } elseif ($filter === 'active') {
        $where_conditions[] = "u.id IN (SELECT DISTINCT user_id FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY))";
    }
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    $stmt = $pdo->prepare("
        SELECT u.*,
               (SELECT COUNT(*) FROM orders WHERE user_id = u.id) as total_orders,
               (SELECT SUM(total) FROM orders WHERE user_id = u.id AND status = 'Delivered') as total_spent,
               (SELECT MAX(created_at) FROM orders WHERE user_id = u.id) as last_order_date
        FROM users u
        $where_clause
        ORDER BY u.created_at DESC
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_users,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_users_7d
        FROM users
    ");
    $user_stats = $stmt->fetch();
} catch(PDOException $e) {
    die("Error loading users: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Wigtopia Admin - User Management</title>
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
        /* Mobile table adjustments */
        .users-table {
            min-width: 100%;
        }
        @media (max-width: 640px) {
            .users-table thead {
                display: none;
            }
            .users-table tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #e5e7eb;
                border-radius: 0.5rem;
                padding: 1rem;
                background-color: #fff;
            }
            .users-table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 0;
                border-bottom: 1px solid #f3f4f6;
            }
            .users-table tbody td:last-child {
                border-bottom: none;
            }
            .users-table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #4b5563;
                text-transform: uppercase;
                font-size: 0.75rem;
            }
            .users-table tbody td .flex {
                justify-content: flex-end;
            }
            /* Modal adjustments */
            #userModal {
                align-items: flex-end;
            }
            #userModal > div {
                width: 100%;
                max-width: none;
                margin: 0;
                border-radius: 0.75rem 0.75rem 0 0;
                max-height: 80vh;
            }
            input, button, select {
                touch-action: manipulation;
            }
        }
        /* Hide scrollbar but keep functionality */
        .overflow-x-auto::-webkit-scrollbar {
            display: none;
        }
        .overflow-x-auto {
            -ms-overflow-style: none;
            scrollbar-width: none;
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
                <a href="users.php" class="flex items-center space-x-3 px-4 py-3 bg-amber-600 text-white rounded-xl">
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
                <a href="settings.php" class="flex items-center space-x-3 px-4 py-3 text-amber-200 hover:bg-amber-700 hover:text-white rounded-xl transition-all duration-300">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>

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
                            <h1 class="text-xl font-bold text-gray-900">User Management</h1>
                            <p class="text-gray-600 text-sm">Manage and monitor all users</p>
                        </div>
                    </div>
                    <div>
                        <button onclick="exportUsers()" class="bg-amber-600 text-white px-3 py-1.5 rounded-lg hover:bg-amber-700 transition-colors text-sm">
                            <i class="fas fa-download mr-1"></i>Export
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- User Statistics -->
        <div class="p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Total Users</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($user_stats['total_users']); ?></p>
                        </div>
                        <i class="fas fa-users text-3xl text-amber-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">New This Month</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($user_stats['new_users_30d']); ?></p>
                        </div>
                        <i class="fas fa-user-plus text-3xl text-green-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">New This Week</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($user_stats['new_users_7d']); ?></p>
                        </div>
                        <i class="fas fa-calendar-plus text-3xl text-blue-500"></i>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="bg-white rounded-xl shadow-sm border p-4 mb-6">
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Search by name or email..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                    </div>
                    <div>
                        <select name="filter" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                            <option value="recent" <?php echo $filter === 'recent' ? 'selected' : ''; ?>>Recent (30 days)</option>
                            <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active (90 days)</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition-colors text-sm">
                        <i class="fas fa-search mr-1"></i>Filter
                    </button>
                </form>
            </div>

            <!-- Users Table -->
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <div class="px-4 py-3 border-b">
                    <h3 class="text-base font-semibold text-gray-900">All Users</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="users-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Spent</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Order</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Joined</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-3 text-center text-gray-500 text-sm">
                                        No users found matching your criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3 whitespace-nowrap" data-label="User">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center">
                                                    <span class="text-amber-600 font-medium text-sm">
                                                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                    </span>
                                                </div>
                                                <div class="ml-3">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($user['name']); ?>
                                                    </div>
                                                    <div class="text-xs text-gray-500">ID: <?php echo $user['id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap" data-label="Contact">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($user['email']); ?></div>
                                            <?php if ($user['phone']): ?>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($user['phone']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap" data-label="Orders">
                                            <span class="text-sm font-medium text-gray-900">
                                                <?php echo number_format($user['total_orders']); ?> orders
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap" data-label="Total Spent">
                                            <span class="text-sm font-medium text-green-600">
                                                $<?php echo number_format($user['total_spent'] ?? 0, 2); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap" data-label="Last Order">
                                            <span class="text-sm text-gray-900">
                                                <?php echo $user['last_order_date'] ? date('M j, Y', strtotime($user['last_order_date'])) : 'Never'; ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap" data-label="Joined">
                                            <span class="text-sm text-gray-900">
                                                <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium" data-label="Actions">
                                            <div class="flex space-x-2">
                                                <button onclick="viewUserDetails(<?php echo $user['id']; ?>)"
                                                        class="text-amber-600 hover:text-amber-900 p-1">
                                                    <i class="fas fa-eye text-sm"></i>
                                                </button>
                                                <button onclick="viewUserOrders(<?php echo $user['id']; ?>)"
                                                        class="text-blue-600 hover:text-blue-900 p-1">
                                                    <i class="fas fa-shopping-cart text-sm"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div id="userModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-[60]">
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 max-h-[80vh] overflow-y-auto">
            <div class="p-4 border-b">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">User Details</h3>
                    <button onclick="closeUserModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
            </div>
            <div id="userModalContent" class="p-4">
                <!-- User details will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        const toggleSidebar = document.getElementById('toggleSidebar');
        const closeSidebar = document.getElementById('closeSidebar');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');

        toggleSidebar.addEventListener('click', () => {
            sidebar.classList.toggle('sidebar-hidden');
            mainContent.classList.toggle('md:ml-64');
        });

        closeSidebar.addEventListener('click', () => {
            sidebar.classList.add('sidebar-hidden');
            mainContent.classList.remove('md:ml-64');
        });

        function viewUserDetails(userId) {
            const modal = document.getElementById('userModal');
            const content = document.getElementById('userModalContent');
            const user = <?php echo json_encode($users); ?>.find(u => u.id == userId);

            if (user) {
                content.innerHTML = `
                    <div class="space-y-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center">
                                <span class="text-xl text-amber-600 font-medium">
                                    ${user.name.charAt(0).toUpperCase()}
                                </span>
                            </div>
                            <div>
                                <h4 class="text-base font-semibold text-gray-900">${user.name}</h4>
                                <p class="text-gray-600 text-sm">${user.email}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Phone</label>
                                <p class="text-gray-900 text-sm">${user.phone || 'Not provided'}</p>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-700">Member Since</label>
                                <p class="text-gray-900 text-sm">${new Date(user.created_at).toLocaleDateString()}</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-gray-700">Address</label>
                            <p class="text-gray-900 text-sm">${user.address || 'Not provided'}</p>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t">
                            <div class="text-center">
                                <p class="text-xl font-bold text-amber-600">${user.total_orders}</p>
                                <p class="text-xs text-gray-600">Total Orders</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xl font-bold text-green-600">$${parseFloat(user.total_spent || 0).toFixed(2)}</p>
                                <p class="text-xs text-gray-600">Total Spent</p>
                            </div>
                            <div class="text-center">
                                <p class="text-xl font-bold text-blue-600">${user.last_order_date ? new Date(user.last_order_date).toLocaleDateString() : 'Never'}</p>
                                <p class="text-xs text-gray-600">Last Order</p>
                            </div>
                        </div>
                    </div>
                `;
                modal.classList.remove('hidden');
            }
        }

        function viewUserOrders(userId) {
            window.location.href = `orders.php?user_id=${userId}`;
        }

        function closeUserModal() {
            document.getElementById('userModal').classList.add('hidden');
        }

        function exportUsers() {
            alert('User export feature would be implemented here');
        }

        document.getElementById('userModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUserModal();
            }
        });
    </script>
    <script src="../public/assets/js/smooth-loader.js"></script>
</body>
</html>
```