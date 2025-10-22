<?php
require_once '../app/core/db.php';
require_admin_login();

// Handle search and filter (unchanged)
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : 'all';
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status_filter !== 'all') {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if ($user_filter) {
    $where_conditions[] = "o.user_id = ?";
    $params[] = $user_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    $stmt = $pdo->prepare("
        SELECT o.*,
               u.name as user_name,
               u.email as user_email,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
               (SELECT GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, ')') SEPARATOR ', ')
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = o.id) as order_items
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        $where_clause
        ORDER BY o.created_at DESC
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_orders,
            COUNT(CASE WHEN status = 'Pending' THEN 1 END) as pending_orders,
            COUNT(CASE WHEN status = 'Delivered' THEN 1 END) as delivered_orders,
            SUM(CASE WHEN status = 'Delivered' THEN total ELSE 0 END) as total_revenue
        FROM orders
    ");
    $order_stats = $stmt->fetch();

    $stmt = $pdo->query("
        SELECT payment_method, COUNT(*) as count, SUM(total) as total_amount
        FROM orders
        GROUP BY payment_method
        ORDER BY count DESC
    ");
    $payment_stats = $stmt->fetchAll();
} catch(PDOException $e) {
    die("Error loading orders: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Wigtopia Admin - Order Management</title>
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
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-processing { background-color: #dbeafe; color: #1e40af; }
        .status-delivered { background-color: #d1fae5; color: #065f46; }
        .status-cancelled { background-color: #fee2e2; color: #991b1b; }
        /* Mobile table adjustments */
        .orders-table {
            min-width: 100%;
        }
        @media (max-width: 640px) {
            .orders-table thead {
                display: none; /* Hide table headers on mobile */
            }
            .orders-table tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #e5e7eb;
                border-radius: 0.5rem;
                padding: 1rem;
                background-color: #fff;
            }
            .orders-table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 0;
                border-bottom: 1px solid #f3f4f6;
            }
            .orders-table tbody td:last-child {
                border-bottom: none;
            }
            .orders-table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                color: #4b5563;
                text-transform: uppercase;
                font-size: 0.75rem;
            }
            .orders-table tbody td .flex {
                justify-content: flex-end;
            }
            /* Modal adjustments */
            #orderModal {
                align-items: flex-end;
            }
            #orderModal > div {
                width: 100%;
                max-width: none;
                margin: 0;
                border-radius: 0.75rem 0.75rem 0 0;
                max-height: 80vh;
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
                <a href="users.php" class="flex items-center space-x-3 px-4 py-3 text-amber-200 hover:bg-amber-700 hover:text-white rounded-xl transition-all duration-300">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                <a href="orders.php" class="flex items-center space-x-3 px-4 py-3 bg-amber-600 text-white rounded-xl">
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
                            <h1 class="text-xl font-bold text-gray-900">Order Management</h1>
                            <p class="text-gray-600 text-sm">Track and manage all customer orders</p>
                        </div>
                    </div>
                    <div>
                        <button onclick="exportOrders()" class="bg-amber-600 text-white px-3 py-1.5 rounded-lg hover:bg-amber-700 transition-colors text-sm">
                            <i class="fas fa-download mr-1"></i>Export
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Order Statistics -->
        <div class="p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Total Orders</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($order_stats['total_orders'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-shopping-cart text-3xl text-amber-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Pending Orders</p>
                            <p class="text-2xl font-bold text-yellow-600"><?php echo number_format($order_stats['pending_orders'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-clock text-3xl text-yellow-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Delivered</p>
                            <p class="text-2xl font-bold text-green-600"><?php echo number_format($order_stats['delivered_orders'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-check-circle text-3xl text-green-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.3s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Total Revenue</p>
                            <p class="text-2xl font-bold text-green-600">$<?php echo number_format($order_stats['total_revenue'] ?? 0, 2); ?></p>
                        </div>
                        <i class="fas fa-dollar-sign text-3xl text-green-500"></i>
                    </div>
                </div>
            </div>

            <!-- Payment Methods Breakdown -->
            <div class="bg-white rounded-xl shadow-sm border p-4 mb-6">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Payment Methods</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                    <?php foreach ($payment_stats as $payment): ?>
                        <div class="text-center p-3 bg-gray-50 rounded-lg">
                            <p class="text-xl font-bold text-amber-600"><?php echo number_format($payment['count'] ?? 0); ?></p>
                            <p class="text-xs text-gray-600"><?php echo htmlspecialchars($payment['payment_method'] ?? ''); ?></p>
                            <p class="text-xs font-medium text-green-600">$<?php echo number_format($payment['total_amount'] ?? 0, 2); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="bg-white rounded-xl shadow-sm border p-4 mb-6">
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Search by ID, name, or email..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                    </div>
                    <div>
                        <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                            <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Processing" <?php echo $status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="Delivered" <?php echo $status_filter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition-colors text-sm">
                        <i class="fas fa-search mr-1"></i>Filter
                    </button>
                </form>
            </div>

            <!-- Orders Table -->
            <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                <div class="px-4 py-3 border-b">
                    <h3 class="text-base font-semibold text-gray-900">All Orders</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="orders-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Items</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="8" class="px-4 py-3 text-center text-gray-500 text-sm">
                                        No orders found matching your criteria.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-4 py-3 whitespace-nowrap" data-label="Order">
                                            <div class="text-sm font-medium text-gray-900">#<?php echo $order['id']; ?></div>
                                            <div class="text-xs text-gray-500"><?php echo $order['item_count']; ?> items</div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap" data-label="Customer">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($order['customer_name'] ?? ''); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($order['customer_email'] ?? ''); ?></div>
                                            <?php if ($order['customer_phone']): ?>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($order['customer_phone']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3" data-label="Items">
                                            <div class="text-xs text-gray-900 max-w-xs truncate" title="<?php echo htmlspecialchars($order['order_items'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($order['order_items'] ?? ''); ?>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap" data-label="Payment">
                                            <div class="text-xs text-gray-900"><?php echo htmlspecialchars($order['payment_method'] ?? ''); ?></div>
                                            <?php if ($order['payment_proof']): ?>
                                                <button onclick="viewPaymentProof('<?php echo $order['payment_proof']; ?>')"
                                                        class="text-xs text-amber-600 hover:text-amber-800">
                                                    View Proof
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap" data-label="Total">
                                            <div class="text-sm font-medium text-gray-900">$<?php echo number_format($order['total'], 2); ?></div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap" data-label="Status">
                                            <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                                <?php echo htmlspecialchars($order['status'] ?? ''); ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap" data-label="Date">
                                            <div class="text-xs text-gray-900"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo date('H:i', strtotime($order['created_at'])); ?></div>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium" data-label="Actions">
                                            <div class="flex space-x-2">
                                                <button onclick="viewOrderDetails(<?php echo $order['id']; ?>)"
                                                        class="text-amber-600 hover:text-amber-900 p-1">
                                                    <i class="fas fa-eye text-sm"></i>
                                                </button>
                                                <select onchange="updateOrderStatus(<?php echo $order['id']; ?>, this.value)"
                                                        class="text-xs border border-gray-300 rounded px-2 py-1">
                                                    <option value="">Update Status</option>
                                                    <option value="Pending" <?php echo $order['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="Processing" <?php echo $order['status'] === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                                                    <option value="Delivered" <?php echo $order['status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                    <option value="Cancelled" <?php echo $order['status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
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

    <!-- Order Details Modal -->
    <div id="orderModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-[60]">
        <div class="bg-white rounded-xl shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="p-4 border-b">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900">Order Details</h3>
                    <button onclick="closeOrderModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
            </div>
            <div id="orderModalContent" class="p-4">
                <!-- Order details will be loaded here -->
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

        function viewOrderDetails(orderId) {
            const modal = document.getElementById('orderModal');
            const content = document.getElementById('orderModalContent');
            const order = <?php echo json_encode($orders); ?>.find(o => o.id == orderId);

            if (order) {
                // Mock AJAX response for demonstration (replace with actual endpoint)
                const mockData = {
                    items: order.order_items.split(', ').map(item => {
                        const matches = item.match(/(.+) \((\d+)\)/);
                        return {
                            product_name: matches ? matches[1] : item,
                            quantity: matches ? parseInt(matches[2]) : 1,
                            price: order.total / order.item_count // Simplified; use actual price from backend
                        };
                    })
                };

                let itemsHtml = '';
                mockData.items.forEach(item => {
                    itemsHtml += `
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div>
                                <p class="font-medium text-sm text-gray-900">${item.product_name}</p>
                                <p class="text-xs text-gray-600">Quantity: ${item.quantity}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-sm text-amber-600">$${parseFloat(item.price).toFixed(2)}</p>
                                <p class="text-xs text-gray-600">Subtotal: $${parseFloat(item.price * item.quantity).toFixed(2)}</p>
                            </div>
                        </div>
                    `;
                });

                content.innerHTML = `
                    <div class="space-y-4">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900 mb-3">Order Information</h4>
                                <div class="space-y-2 text-sm">
                                    <p><span class="font-medium">Order ID:</span> #${order.id}</p>
                                    <p><span class="font-medium">Date:</span> ${new Date(order.created_at).toLocaleString()}</p>
                                    <p><span class="font-medium">Status:</span>
                                        <span class="status-badge status-${order.status.toLowerCase()} ml-2">
                                            ${order.status}
                                        </span>
                                    </p>
                                    <p><span class="font-medium">Payment Method:</span> ${order.payment_method}</p>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-semibold text-gray-900 mb-3">Customer Information</h4>
                                <div class="space-y-2 text-sm">
                                    <p><span class="font-medium">Name:</span> ${order.customer_name}</p>
                                    <p><span class="font-medium">Email:</span> ${order.customer_email}</p>
                                    <p><span class="font-medium">Phone:</span> ${order.customer_phone || 'Not provided'}</p>
                                    <p><span class="font-medium">Address:</span> ${order.customer_address}, ${order.customer_city}, ${order.customer_country}</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-semibold text-gray-900 mb-3">Order Items</h4>
                            <div class="space-y-2">
                                ${itemsHtml}
                            </div>
                        </div>

                        <div class="border-t pt-3">
                            <div class="flex justify-between items-center">
                                <span class="text-base font-bold text-gray-900">Total:</span>
                                <span class="text-base font-bold text-amber-600">$${parseFloat(order.total).toFixed(2)}</span>
                            </div>
                        </div>
                    </div>
                `;
                modal.classList.remove('hidden');
            } else {
                content.innerHTML = '<p class="text-red-600 text-sm">Error loading order details.</p>';
                modal.classList.remove('hidden');
            }
        }

        function updateOrderStatus(orderId, newStatus) {
            if (newStatus && confirm(`Are you sure you want to change order #${orderId} status to ${newStatus}?`)) {
                fetch('update-order-status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `order_id=${orderId}&status=${newStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error updating order status: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating order status');
                });
            }
        }

        function viewPaymentProof(proofFile) {
            window.open(`../uploads/payment_proofs/${proofFile}`, '_blank');
        }

        function exportOrders() {
            alert('Order export feature would be implemented here');
        }

        function closeOrderModal() {
            document.getElementById('orderModal').classList.add('hidden');
        }

        document.getElementById('orderModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeOrderModal();
            }
        });
    </script>
    <script src="../public/assets/js/smooth-loader.js"></script>
</body>
</html> 