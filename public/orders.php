<?php
require_once '../app/core/db.php';

// Require user login
require_login();

// Handle order actions
if (isset($_POST['action'])) {
    $order_id = (int)$_POST['order_id'];
    $action = sanitize_input($_POST['action']);

    try {
        if ($action === 'cancel_order') {
            // Check if order can be cancelled (only pending orders)
            $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND (user_id = ? OR customer_email = ?)");
            $stmt->execute([$order_id, $_SESSION['user_id'], $_SESSION['user_email']]);
            $order = $stmt->fetch();

            if ($order && $order['status'] === 'Pending') {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?");
                $stmt->execute([$order_id]);
                $_SESSION['message'] = "Order #$order_id has been cancelled successfully.";
                header("Location: orders.php");
                exit();
            } else {
                $_SESSION['error'] = "Order cannot be cancelled. Only pending orders can be cancelled.";
            }
        }

        if ($action === 'reorder') {
            // Get order items and add to cart
            $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $items = $stmt->fetchAll();

            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            foreach ($items as $item) {
                if (isset($_SESSION['cart'][$item['product_id']])) {
                    $_SESSION['cart'][$item['product_id']] += $item['quantity'];
                } else {
                    $_SESSION['cart'][$item['product_id']] = $item['quantity'];
                }
            }

            $_SESSION['message'] = "Items have been added to your cart for reorder.";
            header("Location: cart.php");
            exit();
        }

    } catch (PDOException $e) {
        $_SESSION['error'] = "Error processing request. Please try again.";
    }
}

// Get user orders with enhanced information
try {
    $stmt = $pdo->prepare("
        SELECT o.*,
               (SELECT GROUP_CONCAT(CONCAT(p.name, ' (x', oi.quantity, ')') SEPARATOR ', ')
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = o.id) as items_summary,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as total_items
        FROM orders o
        WHERE o.user_id = ? OR o.customer_email = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_email']]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = [];
    error_log("Error fetching orders: " . $e->getMessage());
}

// Get order statistics
$total_spent = 0;
$total_orders = count($orders);
$pending_orders = $delivered_orders = $cancelled_orders = 0;

foreach ($orders as $order) {
    $total_spent += $order['total'];
    switch ($order['status']) {
        case 'Pending': $pending_orders++; break;
        case 'Delivered': $delivered_orders++; break;
        case 'Cancelled': $cancelled_orders++; break;
    }
}

// Handle order export
if (isset($_GET['export'])) {
    $format = sanitize_input($_GET['export']);
    $filename = 'orders_export_' . date('Y-m-d') . '.' . $format;

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename=' . $filename);

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Order ID', 'Date', 'Total', 'Status', 'Items']);

        foreach ($orders as $order) {
            fputcsv($output, [
                $order['id'],
                $order['created_at'],
                '$' . number_format($order['total'], 2),
                $order['status'],
                $order['items_summary']
            ]);
        }
        fclose($output);
        exit();
    }
}

// Function to get status progress percentage
function getStatusProgress($status) {
    $statuses = ['Pending', 'Processing', 'Shipped', 'Delivered'];
    return (array_search($status, $statuses) + 1) / count($statuses) * 100;
}

// Function to get status color
function getStatusColor($status) {
    $colors = [
        'Pending' => 'yellow',
        'Processing' => 'blue',
        'Shipped' => 'purple',
        'Delivered' => 'green',
        'Cancelled' => 'red'
    ];
    return $colors[$status] ?? 'gray';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Elegant Wigs</title>
    <meta name="description" content="View your order history and track your purchases.">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="assets/css/smooth-animations.css" rel="stylesheet">
    <link href="assets/css/dark-mode.css" rel="stylesheet">
    <link href="assets/css/loading-skeleton.css" rel="stylesheet">
    <style>
        .status-badge {
            @apply px-3 py-1 rounded-full text-sm font-medium;
        }
        .status-pending { @apply bg-yellow-100 text-yellow-800; }
        .status-processing { @apply bg-blue-100 text-blue-800; }
        .status-shipped { @apply bg-purple-100 text-purple-800; }
        .status-delivered { @apply bg-green-100 text-green-800; }
        .progress-bar {
            @apply relative h-1 bg-gray-200 rounded-full;
        }
        .progress-fill {
            @apply h-full bg-yellow-500 rounded-full transition-all duration-300;
        }
        .stat-card {
            @apply bg-gradient-to-br from-gray-50 to-gray-100 p-6 rounded-xl shadow-md text-center transform transition-all duration-300 hover:scale-105;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <?php include 'header.php'; ?>
    
    <main class="container mx-auto px-4 py-8 md:py-12">
        <div class="mb-8 animate fade-in">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">My Orders</h1>
                    <p class="text-gray-600">Track your orders and view your purchase history</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="accounts.php" class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition-all duration-300 font-medium">
                        ← Back to Account
                    </a>
                    <?php if (!empty($orders)): ?>
                        <a href="?export=csv" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition-all duration-300 font-medium">
                            <i class="fas fa-download mr-2"></i>Export CSV
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 animate fade-in">
                <i class="fas fa-check-circle mr-2"></i><?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 animate fade-in">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
            <div class="text-center py-12 bg-white rounded-2xl shadow-lg animate fade-in">
                <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">No Orders Yet</h3>
                <p class="text-gray-500 mb-6">You haven't placed any orders yet. Start shopping to see your order history here!</p>
                <a href="products.php" class="bg-amber-600 text-white px-6 py-3 rounded-lg hover:bg-amber-700 transition-all duration-300 font-medium inline-block">
                    Start Shopping
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($orders as $order): ?>
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden animate fade-in hover:shadow-xl transition-shadow duration-300">
                        <!-- Order Header -->
                        <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Order Number</p>
                                <p class="font-semibold text-amber-600">#<?php echo $order['id']; ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Order Date</p>
                                <p class="font-medium text-gray-800"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Total Amount</p>
                                <p class="font-semibold text-amber-600">$<?php echo number_format($order['total'], 2); ?></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 mb-1">Status</p>
                                <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                    <?php echo htmlspecialchars($order['status']); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Order Details -->
                        <div class="p-6">
                            <div class="mb-4">
                                <h4 class="text-lg font-semibold text-gray-800 mb-2">Items Ordered:</h4>
                                <p class="text-gray-600"><?php echo htmlspecialchars($order['items_summary'] ?? 'No items found'); ?></p>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                <div>
                                    <h5 class="text-md font-semibold text-gray-800 mb-2">Shipping Address:</h5>
                                    <p class="text-gray-600">
                                        <?php echo htmlspecialchars($order['customer_name'] ?? ''); ?><br>
                                        <?php echo htmlspecialchars($order['customer_address'] ?? ''); ?><br>
                                        <?php echo htmlspecialchars($order['customer_city'] ?? ''); ?>, <?php echo htmlspecialchars($order['customer_country'] ?? ''); ?>
                                    </p>
                                </div>
                                <div>
                                    <h5 class="text-md font-semibold text-gray-800 mb-2">Payment Method:</h5>
                                    <p class="text-gray-600">
                                        <?php echo htmlspecialchars($order['payment_method'] ?? 'Not specified'); ?>
                                        <?php if (!empty($order['payment_proof'])): ?>
                                            <br><small class="text-green-600">✓ Payment proof uploaded</small>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Enhanced Order Actions -->
                            <div class="flex flex-wrap gap-3 mt-4">
                                <a href="order-details.php?order_id=<?php echo $order['id']; ?>" class="inline-flex items-center bg-blue-100 text-blue-700 px-4 py-2 rounded-lg hover:bg-blue-200 transition-colors">
                                    <i class="fas fa-eye mr-2"></i> View Details
                                </a>

                                <?php if ($order['status'] === 'Pending'): ?>
                                    <form method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to cancel this order?')">
                                        <input type="hidden" name="action" value="cancel_order">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <button type="submit" class="inline-flex items-center bg-red-100 text-red-700 px-4 py-2 rounded-lg hover:bg-red-200 transition-colors">
                                            <i class="fas fa-times mr-2"></i> Cancel Order
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($order['status'] === 'Delivered'): ?>
                                    <form method="POST" class="inline-block">
                                        <input type="hidden" name="action" value="reorder">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <button type="submit" class="inline-flex items-center bg-green-100 text-green-700 px-4 py-2 rounded-lg hover:bg-green-200 transition-colors">
                                            <i class="fas fa-redo mr-2"></i> Buy Again
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($order['status'] === 'Shipped'): ?>
                                    <span class="inline-flex items-center bg-purple-100 text-purple-700 px-4 py-2 rounded-lg">
                                        <i class="fas fa-truck mr-2"></i> Out for Delivery
                                    </span>
                                <?php endif; ?>

                                <?php if ($order['status'] === 'Processing'): ?>
                                    <span class="inline-flex items-center bg-yellow-100 text-yellow-700 px-4 py-2 rounded-lg">
                                        <i class="fas fa-clock mr-2"></i> Processing
                                    </span>
                                <?php endif; ?>

                                <?php if ($order['status'] === 'Cancelled'): ?>
                                    <span class="inline-flex items-center bg-red-100 text-red-700 px-4 py-2 rounded-lg">
                                        <i class="fas fa-ban mr-2"></i> Cancelled
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Order Progress Bar -->
                        <div class="bg-gray-50 p-4">
                            <?php
                            if ($order['status'] === 'Cancelled') {
                                // Special handling for cancelled orders
                                ?>
                                <div class="flex justify-between items-center">
                                    <div class="text-center flex-1">
                                        <div class="w-8 h-8 rounded-full bg-red-500 text-white flex items-center justify-center mx-auto mb-2">
                                            <i class="fas fa-ban text-sm"></i>
                                        </div>
                                        <p class="text-xs text-red-600 font-medium">Cancelled</p>
                                    </div>
                                </div>
                                <?php
                            } else {
                                // Normal progress bar for active orders
                                $statuses = ['Pending', 'Processing', 'Shipped', 'Delivered'];
                                $current_status_index = array_search($order['status'], $statuses);
                                $progress_percentage = ($current_status_index + 1) / count($statuses) * 100;
                                ?>
                                <div class="flex justify-between items-center relative">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width: <?php echo $progress_percentage; ?>%"></div>
                                    </div>
                                    <?php foreach ($statuses as $index => $status): ?>
                                        <div class="flex flex-col items-center relative z-10">
                                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm <?php echo $index <= $current_status_index ? 'bg-yellow-500 text-white' : 'bg-gray-200 text-gray-500'; ?>">
                                                <?php
                                                $icons = ['fa-hourglass-start', 'fa-sync', 'fa-shipping-fast', 'fa-check'];
                                                echo '<i class="fas ' . $icons[$index] . '"></i>';
                                                ?>
                                            </div>
                                            <p class="text-xs mt-2 <?php echo $index <= $current_status_index ? 'text-gray-800 font-medium' : 'text-gray-500'; ?>">
                                                <?php echo $status; ?>
                                            </p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Enhanced Order Statistics -->
                <div class="bg-gradient-to-r from-amber-50 to-amber-100 p-8 rounded-2xl shadow-lg mt-8 animate fade-in">
                    <h3 class="text-2xl font-bold text-gray-800 text-center mb-8 flex items-center justify-center gap-2">
                        <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Your Order Statistics
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        <div class="bg-white/80 backdrop-blur-sm p-4 rounded-xl shadow-md text-center hover:shadow-lg transition-shadow duration-300">
                            <div class="text-2xl font-bold text-blue-600 mb-1"><?php echo $total_orders; ?></div>
                            <div class="text-gray-600 font-medium text-sm">Total Orders</div>
                        </div>
                        <div class="bg-white/80 backdrop-blur-sm p-4 rounded-xl shadow-md text-center hover:shadow-lg transition-shadow duration-300">
                            <div class="text-2xl font-bold text-green-600 mb-1">$<?php echo number_format($total_spent, 2); ?></div>
                            <div class="text-gray-600 font-medium text-sm">Total Spent</div>
                        </div>
                        <div class="bg-white/80 backdrop-blur-sm p-4 rounded-xl shadow-md text-center hover:shadow-lg transition-shadow duration-300">
                            <div class="text-2xl font-bold text-purple-600 mb-1"><?php echo $delivered_orders; ?></div>
                            <div class="text-gray-600 font-medium text-sm">Delivered</div>
                        </div>
                        <div class="bg-white/80 backdrop-blur-sm p-4 rounded-xl shadow-md text-center hover:shadow-lg transition-shadow duration-300">
                            <div class="text-2xl font-bold text-yellow-600 mb-1"><?php echo $pending_orders; ?></div>
                            <div class="text-gray-600 font-medium text-sm">Pending</div>
                        </div>
                        <div class="bg-white/80 backdrop-blur-sm p-4 rounded-xl shadow-md text-center hover:shadow-lg transition-shadow duration-300">
                            <div class="text-2xl font-bold text-red-600 mb-1"><?php echo $cancelled_orders; ?></div>
                            <div class="text-gray-600 font-medium text-sm">Cancelled</div>
                        </div>
                        <div class="bg-white/80 backdrop-blur-sm p-4 rounded-xl shadow-md text-center hover:shadow-lg transition-shadow duration-300">
                            <div class="text-2xl font-bold text-indigo-600 mb-1">$<?php echo $total_orders > 0 ? number_format($total_spent / $total_orders, 2) : '0.00'; ?></div>
                            <div class="text-gray-600 font-medium text-sm">Avg Order</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'footer.php'; ?>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/smooth-loader.js"></script>
    <script src="assets/js/loading-skeleton.js"></script>
    <script src="assets/js/dark-mode.js"></script>
</body>
</html>