<?php
require_once '../app/core/db.php';
require_admin_login();

// Get comprehensive analytics data (unchanged)
try {
    $stmt = $pdo->query("
        SELECT
            DATE_FORMAT(created_at, '%Y-%m') as month,
            SUM(total) as revenue,
            COUNT(*) as order_count,
            AVG(total) as avg_order_value
        FROM orders
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $monthly_revenue = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT
            p.name,
            p.category,
            SUM(oi.quantity) as total_sold,
            SUM(oi.quantity * oi.price) as revenue,
            COUNT(DISTINCT o.id) as order_count
        FROM products p
        JOIN order_items oi ON p.id = oi.product_id
        JOIN orders o ON oi.order_id = o.id
        GROUP BY p.id, p.name, p.category
        ORDER BY total_sold DESC
        LIMIT 10
    ");
    $top_products = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT
            c.name as category_name,
            c.icon,
            COUNT(p.id) as product_count,
            SUM(oi.quantity) as total_sold,
            SUM(oi.quantity * oi.price) as revenue
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id
        GROUP BY c.id, c.name, c.icon
        ORDER BY revenue DESC
    ");
    $category_performance = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_users,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_users_7d,
            (SELECT COUNT(DISTINCT user_id) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as active_users_30d
        FROM users
    ");
    $user_analytics = $stmt->fetch();

    $stmt = $pdo->query("
        SELECT
            payment_method,
            COUNT(*) as order_count,
            SUM(total) as total_amount,
            AVG(total) as avg_amount
        FROM orders
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ");
    $payment_analytics = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT
            status,
            COUNT(*) as count
        FROM orders
        GROUP BY status
        ORDER BY count DESC
    ");
    $order_status = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT
            DATE(created_at) as date,
            COUNT(*) as orders,
            SUM(total) as revenue
        FROM orders
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date DESC
        LIMIT 30
    ");
    $recent_activity = $stmt->fetchAll();
} catch(PDOException $e) {
    die("Error loading analytics: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Wigtopia Admin - Analytics</title>
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
        .gradient-bg {
            background: linear-gradient(135deg, #92400e, #d97706, #f59e0b);
        }
        /* Ensure bars in revenue trend are touch-friendly */
        .bar-container {
            touch-action: manipulation;
        }
        /* Hide scrollbar on mobile but keep functionality */
        .overflow-x-auto::-webkit-scrollbar {
            display: none;
        }
        .overflow-x-auto {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        /* Responsive table adjustments */
        @media (max-width: 640px) {
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            thead {
                display: none;
            }
            tbody tr {
                display: block;
                margin-bottom: 1rem;
                border-bottom: 1px solid #e5e7eb;
            }
            tbody td {
                display: block;
                text-align: left !important;
                padding: 0.5rem 1rem;
                position: relative;
                padding-left: 8rem;
            }
            tbody td:before {
                content: attr(data-label);
                position: absolute;
                left: 1rem;
                width: 7rem;
                font-weight: bold;
                text-transform: uppercase;
                color: #6b7280;
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
                <a href="analytics.php" class="flex items-center space-x-3 px-4 py-3 bg-amber-600 text-white rounded-xl">
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
                            <h1 class="text-xl font-bold text-gray-900">Analytics Dashboard</h1>
                            <p class="text-gray-600 text-sm">Comprehensive business insights</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <select id="timeRange" class="px-3 py-1.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                            <option value="30">Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="365">Last Year</option>
                        </select>
                        <button onclick="exportAnalytics()" class="bg-amber-600 text-white px-3 py-1.5 rounded-lg hover:bg-amber-700 transition-colors text-sm">
                            <i class="fas fa-download mr-1"></i>Export
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Analytics Content -->
        <div class="p-4 space-y-6">
            <!-- Key Metrics Overview -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Total Users</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($user_analytics['total_users']); ?></p>
                            <p class="text-xs <?php echo $user_analytics['new_users_7d'] > 0 ? 'text-green-600' : 'text-gray-500'; ?>">
                                +<?php echo number_format($user_analytics['new_users_7d']); ?> this week
                            </p>
                        </div>
                        <i class="fas fa-users text-3xl text-amber-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Active Users (30d)</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($user_analytics['active_users_30d']); ?></p>
                            <p class="text-xs text-gray-500">Recent purchasers</p>
                        </div>
                        <i class="fas fa-user-check text-3xl text-green-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Total Revenue</p>
                            <p class="text-2xl font-bold text-gray-900">
                                $<?php echo number_format(array_sum(array_column($monthly_revenue, 'revenue')), 2); ?>
                            </p>
                            <p class="text-xs text-gray-500">Last 12 months</p>
                        </div>
                        <i class="fas fa-dollar-sign text-3xl text-green-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.3s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Avg Order Value</p>
                            <p class="text-2xl font-bold text-gray-900">
                                $<?php echo number_format(count($monthly_revenue) > 0 ? array_sum(array_column($monthly_revenue, 'avg_order_value')) / count($monthly_revenue) : 0, 2); ?>
                            </p>
                            <p class="text-xs text-gray-500">Per transaction</p>
                        </div>
                        <i class="fas fa-chart-line text-3xl text-blue-500"></i>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 gap-6">
                <!-- Revenue Trend -->
                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.4s;">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Revenue Trend (12 Months)</h3>
                    <div class="h-48 flex items-end justify-between space-x-1 bar-container">
                        <?php foreach ($monthly_revenue as $month): ?>
                            <?php
                            $max_revenue = max(array_column($monthly_revenue, 'revenue'));
                            $height = $max_revenue > 0 ? ($month['revenue'] / $max_revenue) * 100 : 0;
                            ?>
                            <div class="flex flex-col items-center flex-1">
                                <div class="w-full bg-amber-500 rounded-t" style="height: <?php echo $height; ?>%; min-height: 4px;" title="$<?php echo number_format($month['revenue'], 2); ?>"></div>
                                <span class="text-xs text-gray-500 mt-2 transform -rotate-45 origin-top-left sm:rotate-0">
                                    <?php echo date('M', strtotime($month['month'] . '-01')); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Payment Methods -->
                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.5s;">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Payment Methods</h3>
                    <div class="space-y-3">
                        <?php foreach ($payment_analytics as $payment): ?>
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <div class="w-3 h-3 bg-amber-500 rounded-full"></div>
                                    <span class="text-sm text-gray-900"><?php echo htmlspecialchars($payment['payment_method']); ?></span>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-sm text-gray-900"><?php echo number_format($payment['order_count']); ?> orders</p>
                                    <p class="text-xs text-gray-600">$<?php echo number_format($payment['total_amount'], 2); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Top Products & Categories -->
            <div class="grid grid-cols-1 gap-6">
                <!-- Top Products -->
                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.6s;">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Top Products</h3>
                    <div class="space-y-3">
                        <?php foreach ($top_products as $index => $product): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-2">
                                    <span class="w-6 h-6 bg-amber-100 text-amber-600 rounded-full flex items-center justify-center text-xs font-bold">
                                        <?php echo $index + 1; ?>
                                    </span>
                                    <div>
                                        <p class="font-medium text-sm text-gray-900"><?php echo htmlspecialchars($product['name']); ?></p>
                                        <p class="text-xs text-gray-600"><?php echo htmlspecialchars($product['category']); ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-sm text-amber-600"><?php echo number_format($product['total_sold']); ?> sold</p>
                                    <p class="text-xs text-gray-600">$<?php echo number_format($product['revenue'], 2); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Category Performance -->
                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.7s;">
                    <h3 class="text-base font-semibold text-gray-900 mb-3">Category Performance</h3>
                    <div class="space-y-3">
                        <?php foreach ($category_performance as $category): ?>
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center space-x-2">
                                    <span class="text-xl"><?php echo htmlspecialchars($category['icon']); ?></span>
                                    <div>
                                        <p class="font-medium text-sm text-gray-900"><?php echo htmlspecialchars($category['category_name']); ?></p>
                                        <p class="text-xs text-gray-600"><?php echo number_format($category['product_count']); ?> products</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="font-semibold text-sm text-green-600">$<?php echo number_format($category['revenue'] ?? 0, 2); ?></p>
                                    <p class="text-xs text-gray-600"><?php echo number_format($category['total_sold'] ?? 0); ?> sold</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.8s;">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Recent Activity (Last 30 Days)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Orders</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($recent_activity as $activity): ?>
                                <tr>
                                    <td data-label="Date" class="px-4 py-3 text-sm text-gray-900">
                                        <?php echo date('M j, Y', strtotime($activity['date'])); ?>
                                    </td>
                                    <td data-label="Orders" class="px-4 py-3 text-sm text-gray-900">
                                        <?php echo number_format($activity['orders']); ?>
                                    </td>
                                    <td data-label="Revenue" class="px-4 py-3 text-sm text-gray-900">
                                        $<?php echo number_format($activity['revenue'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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

        function exportAnalytics() {
            alert('Analytics export feature would be implemented here');
        }

        document.getElementById('timeRange').addEventListener('change', function() {
            const days = this.value;
            console.log('Time range changed to:', days, 'days');
        });
    </script>
    <script src="../public/assets/js/smooth-loader.js"></script>
</body>
</html>
