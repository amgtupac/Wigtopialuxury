<?php
require_once '../app/core/db.php';
require_admin_login();

// Fetch dashboard statistics
try {
    // Total revenue and orders
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_orders,
            SUM(total) as total_revenue,
            COUNT(DISTINCT user_id) as active_customers
        FROM orders 
        WHERE status = 'Delivered'
    ");
    $order_stats = $stmt->fetch();

    // Recent orders (last 5)
    $stmt = $pdo->query("
        SELECT o.*, u.name as user_name
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $recent_orders = $stmt->fetchAll();

    // Top products (by quantity sold)
    $stmt = $pdo->query("
        SELECT p.name, SUM(oi.quantity) as total_sold
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        GROUP BY p.id, p.name
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $top_products = $stmt->fetchAll();

    // User statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users
        FROM users
    ");
    $user_stats = $stmt->fetch();

    // Product statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_products,
            COUNT(CASE WHEN stock <= 5 THEN 1 END) as low_stock_products
        FROM products
    ");
    $product_stats = $stmt->fetch();

    // User activity log (last 20 activities)
    $stmt = $pdo->query("
        SELECT ual.*, u.name as user_name, u.email as user_email
        FROM user_activity_log ual
        LEFT JOIN users u ON ual.user_id = u.id
        ORDER BY ual.timestamp DESC
        LIMIT 20
    ");
    $user_activities = $stmt->fetchAll();

    // Activity statistics
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_activities,
            COUNT(CASE WHEN timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as activities_24h,
            COUNT(CASE WHEN action = 'login' AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as logins_24h
        FROM user_activity_log
    ");
    $activity_stats = $stmt->fetch();

} catch(PDOException $e) {
    die("Error loading dashboard data: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Wigtopia Admin - Dashboard</title>
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
        @media (max-width: 640px) {
            .card-hover {
                transform: none;
            }
            .card-hover:hover {
                transform: none;
                box-shadow: none;
            }
            input, button, select {
                touch-action: manipulation;
            }
            .recent-orders-table thead {
                display: none;
            }
            .recent-orders-table tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #e5e7eb;
                border-radius: 0.5rem;
                padding: 1rem;
                background-color: #fff;
            }
            .recent-orders-table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 0;
                border-bottom: 1px solid #f3f4f6;
            }
            .recent-orders-table tbody td:last-child {
                border-bottom: none;
            }
            .recent-orders-table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #4b5563;
                text-transform: uppercase;
                font-size: 0.75rem;
            }
        }
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
                <a href="dashboard.php" class="flex items-center space-x-3 px-4 py-3 bg-amber-600 text-white rounded-xl">
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
                            <h1 class="text-xl font-bold text-gray-900">Dashboard</h1>
                            <p class="text-gray-600 text-sm">Overview of your store's performance</p>
                        </div>
                    </div>
                    <div>
                        <button onclick="exportData()" class="bg-amber-600 text-white px-3 py-1.5 rounded-lg hover:bg-amber-700 transition-colors text-sm">
                            <i class="fas fa-download mr-1"></i>Export Data
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Dashboard Content -->
        <div class="p-4">
            <!-- Key Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm border p-4 card-hover slide-in-up">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Total Revenue</p>
                            <p class="text-2xl font-bold text-green-600">$<?php echo number_format($order_stats['total_revenue'] ?? 0, 2); ?></p>
                        </div>
                        <i class="fas fa-dollar-sign text-3xl text-green-500"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 card-hover slide-in-up" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Total Orders</p>
                            <p class="text-2xl font-bold text-amber-600"><?php echo number_format($order_stats['total_orders']); ?></p>
                        </div>
                        <i class="fas fa-shopping-cart text-3xl text-amber-500"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 card-hover slide-in-up" style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Active Customers</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo number_format($order_stats['active_customers']); ?></p>
                        </div>
                        <i class="fas fa-users text-3xl text-blue-500"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 card-hover slide-in-up" style="animation-delay: 0.3s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Low Stock Products</p>
                            <p class="text-2xl font-bold text-red-600"><?php echo number_format($product_stats['low_stock_products']); ?></p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-3xl text-red-500"></i>
                    </div>
                </div>
            </div>

            <!-- Recent Activity and Quick Actions -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">
                <!-- Recent Orders -->
                <div class="bg-white rounded-xl shadow-sm border">
                    <div class="px-4 py-3 border-b">
                        <h3 class="text-base font-semibold text-gray-900">Recent Orders</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="recent-orders-table w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order ID</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php if (empty($recent_orders)): ?>
                                    <tr>
                                        <td colspan="5" class="px-4 py-3 text-center text-gray-500 text-sm">
                                            No recent orders found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-4 py-3 whitespace-nowrap" data-label="Order ID">
                                                <span class="text-sm text-gray-900">#<?php echo htmlspecialchars($order['id']); ?></span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap" data-label="Customer">
                                                <span class="text-sm text-gray-900"><?php echo htmlspecialchars($order['user_name'] ?? 'Guest'); ?></span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap" data-label="Total">
                                                <span class="text-sm font-medium text-green-600">$<?php echo number_format($order['total'], 2); ?></span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap" data-label="Status">
                                                <span class="text-sm px-2 py-1 rounded-full <?php echo $order['status'] === 'Delivered' ? 'bg-green-100 text-green-800' : ($order['status'] === 'Processing' ? 'bg-amber-100 text-amber-800' : 'bg-red-100 text-red-800'); ?>">
                                                    <?php echo htmlspecialchars($order['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap" data-label="Date">
                                                <span class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-xl shadow-sm border p-4">
                    <h3 class="text-base font-semibold text-gray-900 mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <a href="products.php#openAddProductModal" class="bg-amber-600 text-white px-3 py-2 rounded-lg hover:bg-amber-700 transition-colors text-sm text-center flex items-center justify-center">
                            <i class="fas fa-plus mr-1"></i>Add Product
                        </a>
                        <a href="orders.php" class="bg-blue-600 text-white px-3 py-2 rounded-lg hover:bg-blue-700 transition-colors text-sm text-center flex items-center justify-center">
                            <i class="fas fa-shopping-cart mr-1"></i>View Orders
                        </a>
                        <a href="users.php" class="bg-green-600 text-white px-3 py-2 rounded-lg hover:bg-green-700 transition-colors text-sm text-center flex items-center justify-center">
                            <i class="fas fa-users mr-1"></i>Manage Users
                        </a>
                        <a href="analytics.php" class="bg-purple-600 text-white px-3 py-2 rounded-lg hover:bg-purple-700 transition-colors text-sm text-center flex items-center justify-center">
                            <i class="fas fa-chart-bar mr-1"></i>View Analytics
                        </a>
                    </div>
                </div>
            </div>

            <!-- User Activity Statistics -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-xl shadow-sm p-4 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-100 text-xs">Total Activities</p>
                            <p class="text-2xl font-bold"><?php echo number_format($activity_stats['total_activities'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-chart-line text-3xl text-purple-200"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-xl shadow-sm p-4 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-100 text-xs">Activities (24h)</p>
                            <p class="text-2xl font-bold"><?php echo number_format($activity_stats['activities_24h'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-clock text-3xl text-blue-200"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-xl shadow-sm p-4 text-white">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-100 text-xs">Logins (24h)</p>
                            <p class="text-2xl font-bold"><?php echo number_format($activity_stats['logins_24h'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-sign-in-alt text-3xl text-green-200"></i>
                    </div>
                </div>
            </div>

            <!-- User Activity Log -->
            <div class="bg-white rounded-xl shadow-sm border mb-6">
                <div class="px-4 py-3 border-b flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">User Activity Log</h3>
                    <span class="text-xs text-gray-500">Last 20 activities</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">IP Address</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($user_activities)): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-3 text-center text-gray-500 text-sm">
                                        No user activities recorded yet.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($user_activities as $activity): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="w-8 h-8 bg-gradient-to-r from-purple-400 to-purple-600 rounded-full flex items-center justify-center text-white font-semibold text-xs">
                                                    <?php echo strtoupper(substr($activity['user_name'] ?? 'U', 0, 1)); ?>
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($activity['user_name'] ?? 'Unknown'); ?></p>
                                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($activity['user_email'] ?? ''); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <?php
                                            $action_colors = [
                                                'login' => 'bg-green-100 text-green-800',
                                                'login_failed' => 'bg-red-100 text-red-800',
                                                'register' => 'bg-blue-100 text-blue-800',
                                                'order_placed' => 'bg-purple-100 text-purple-800',
                                                'logout' => 'bg-gray-100 text-gray-800'
                                            ];
                                            $color_class = $action_colors[$activity['action']] ?? 'bg-gray-100 text-gray-800';
                                            ?>
                                            <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $color_class; ?>">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($activity['action']))); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <p class="text-sm text-gray-600 truncate max-w-xs" title="<?php echo htmlspecialchars($activity['details'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($activity['details'] ?? '-'); ?>
                                            </p>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="text-xs text-gray-500 font-mono"><?php echo htmlspecialchars($activity['ip_address'] ?? '-'); ?></span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="text-xs text-gray-500"><?php echo date('M j, Y g:i A', strtotime($activity['timestamp'])); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Products -->
            <div class="bg-white rounded-xl shadow-sm border">
                <div class="px-4 py-3 border-b">
                    <h3 class="text-base font-semibold text-gray-900">Top Products</h3>
                </div>
                <div class="p-4">
                    <?php if (empty($top_products)): ?>
                        <p class="text-center text-gray-500 text-sm">No products sold yet.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($top_products as $index => $product): ?>
                                <div class="flex items-center justify-between slide-in-up" style="animation-delay: <?php echo ($index * 0.1) + 0.4; ?>s;">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-8 h-8 bg-amber-100 rounded-full flex items-center justify-center">
                                            <span class="text-amber-600 font-medium text-sm"><?php echo $index + 1; ?></span>
                                        </div>
                                        <span class="text-sm text-gray-900 truncate"><?php echo htmlspecialchars($product['name'] ?? ''); ?></span>
                                    </div>
                                    <span class="text-sm font-medium text-amber-600"><?php echo number_format($product['total_sold']); ?> sold</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
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

        function exportData() {
            alert('Dashboard data export feature would be implemented here');
        }
    </script>
    <script src="../public/assets/js/smooth-loader.js"></script>
</body>
</html>
