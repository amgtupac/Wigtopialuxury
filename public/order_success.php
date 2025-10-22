<?php
require_once __DIR__ . '/../app/core/db.php';
require_login();

// Check if there's a recent order in session
if (!isset($_SESSION['last_order'])) {
    header("Location: index.php");
    exit();
}

$order = $_SESSION['last_order'];
$is_cod = ($order['payment_method'] === 'Cash on Delivery');

// Clear the order from session to prevent refresh issues
unset($_SESSION['last_order']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Elegant Wigs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .checkmark-circle {
            width: 80px;
            height: 80px;
            position: relative;
            display: inline-block;
            vertical-align: top;
        }
        .checkmark-circle .background {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: #DCFCE7;
            position: absolute;
        }
        .checkmark-circle .checkmark {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            color: #22C55E;
            font-size: 40px;
        }
        .order-details {
            background: #F8FAFC;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'header.php'; ?>

    <main class="container mx-auto px-4 py-12 max-w-4xl">
        <div class="text-center mb-12">
            <div class="checkmark-circle mx-auto mb-6">
                <div class="background"></div>
                <i class="checkmark fas fa-check-circle"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 mb-3">Thank You for Your Order!</h1>
            <p class="text-gray-600 mb-6">Your order has been received and is being processed.</p>
            <div class="bg-green-50 text-green-800 inline-flex items-center px-4 py-2 rounded-full text-sm font-medium mb-8">
                <i class="fas fa-info-circle mr-2"></i>
                Order #<?php echo htmlspecialchars($order['order_id']); ?>
            </div>
        </div>

        <div class="order-details p-8 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-6">Order Details</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="font-medium text-gray-900 mb-3">Order Information</h3>
                    <ul class="space-y-2 text-gray-600">
                        <li class="flex justify-between">
                            <span>Order Number:</span>
                            <span class="font-medium text-gray-900">#<?php echo htmlspecialchars($order['order_id']); ?></span>
                        </li>
                        <li class="flex justify-between">
                            <span>Date:</span>
                            <span class="text-gray-900"><?php echo date('F j, Y'); ?></span>
                        </li>
                        <li class="flex justify-between">
                            <span>Total:</span>
                            <span class="font-semibold text-amber-600">$<?php echo number_format($order['total'], 2); ?></span>
                        </li>
                        <li class="flex justify-between">
                            <span>Payment Method:</span>
                            <span class="text-gray-900"><?php echo htmlspecialchars($order['payment_method']); ?></span>
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="font-medium text-gray-900 mb-3">Customer Information</h3>
                    <ul class="space-y-2 text-gray-600">
                        <li class="flex justify-between">
                            <span>Name:</span>
                            <span class="text-gray-900"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                        </li>
                        <li class="flex justify-between">
                            <span>Email:</span>
                            <span class="text-gray-900"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <?php if ($is_cod): ?>
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-8">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-info-circle text-blue-500 mt-1 mr-3"></i>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-blue-800">Cash on Delivery</h3>
                    <div class="mt-2 text-sm text-blue-700">
                        <p>Your order will be processed for delivery. Please have the exact amount ready for our delivery personnel.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="text-center mt-10">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">What's Next?</h2>
            <p class="text-gray-600 mb-6">
                <?php if ($is_cod): ?>
                    We'll send you shipping confirmation when your order is on its way!
                <?php else: ?>
                    You'll receive an email confirmation with your order details and tracking information.
                <?php endif; ?>
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-4 mt-8">
                <a href="products.php" class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                    <i class="fas fa-shopping-cart mr-2"></i> Continue Shopping
                </a>
                <a href="orders.php" class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                    <i class="fas fa-clipboard-list mr-2"></i> View My Orders
                </a>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>
