<?php
require_once '../app/core/db.php';
require_login();

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($order_id <= 0) {
    header("Location: orders.php");
    exit();
}

// Get order details
try {
    $stmt = $pdo->prepare("
        SELECT o.* 
        FROM orders o 
        WHERE o.id = ? AND (o.user_id = ? OR o.customer_email = ?)
    ");
    $stmt->execute([$order_id, $_SESSION['user_id'], $_SESSION['user_email']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $_SESSION['error'] = 'Order not found or you do not have permission to view it.';
        header("Location: orders.php");
        exit();
    }
} catch (PDOException $e) {
    error_log("Order fetch error: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading order details.';
    header("Location: orders.php");
    exit();
}

// Get order items with product details including images and main_image_index
try {
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name, p.images, p.main_image_index
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Order items fetch error: " . $e->getMessage());
    $order_items = [];
}

// Get existing reviews for this order's products
try {
    $stmt = $pdo->prepare("
        SELECT r.* 
        FROM reviews r
        JOIN order_items oi ON r.product_id = oi.product_id
        WHERE oi.order_id = ? AND r.user_id = ?
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $existing_reviews = [];
    foreach ($reviews as $review) {
        if (!isset($existing_reviews[$review['product_id']])) {
            $existing_reviews[$review['product_id']] = [];
        }
        $existing_reviews[$review['product_id']][] = $review;
    }
} catch (PDOException $e) {
    $existing_reviews = [];
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $product_id = (int)$_POST['product_id'];
    $rating = (int)$_POST['rating'];
    $review_text = sanitize_input($_POST['review_text']);
    
    if ($product_id > 0 && $rating >= 1 && $rating <= 5) {
        try {
            // Check if review already exists
            $stmt = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
            $stmt->execute([$product_id, $_SESSION['user_id']]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update existing review
                $stmt = $pdo->prepare("UPDATE reviews SET rating = ?, review_text = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$rating, $review_text, $existing['id']]);
                $_SESSION['message'] = 'Review updated successfully!';
            } else {
                // Insert new review
                $stmt = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, review_text) VALUES (?, ?, ?, ?)");
                $stmt->execute([$product_id, $_SESSION['user_id'], $rating, $review_text]);
                $_SESSION['message'] = 'Review submitted successfully!';
            }
            
            header("Location: order-details.php?order_id=" . $order_id);
            exit();
        } catch (PDOException $e) {
            $_SESSION['error'] = 'Error submitting review. Please try again.';
            error_log("Review submission error: " . $e->getMessage());
        }
    }
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
    <title>Order #<?php echo $order_id; ?> Details - Elegant Wigs</title>
    <meta name="description" content="View detailed information about your order.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .status-badge {
            @apply px-3 py-1 rounded-full text-sm font-medium;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .review-stars {
            display: inline-block;
            direction: rtl;
            unicode-bidi: bidi-override;
        }
        .review-stars input[type="radio"] {
            display: none;
        }
        .review-stars label {
            color: #ddd;
            font-size: 20px;
            cursor: pointer;
            transition: color 0.2s;
        }
        .review-stars input[type="radio"]:checked ~ label,
        .review-stars label:hover,
        .review-stars label:hover ~ label {
            color: #ffd700;
        }
    </style>
</head>
<body class="bg-gray-100 font-sans">
    <?php include 'header.php'; ?>

    <main class="container mx-auto px-4 py-8 md:py-12">
        <!-- Header -->
        <div class="mb-8 animate fade-in">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">Order #<?php echo $order_id; ?></h1>
                    <p class="text-gray-600">Order placed on <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                </div>
                <a href="orders.php" class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition-all duration-300 font-medium">
                    ← Back to Orders
                </a>
            </div>

            <!-- Status Badge -->
            <div class="flex items-center gap-4">
                <span class="status-badge bg-<?php echo getStatusColor($order['status']); ?>-100 text-<?php echo getStatusColor($order['status']); ?>-800">
                    <i class="fas fa-circle mr-2"></i><?php echo htmlspecialchars($order['status']); ?>
                </span>
                <?php if ($order['status'] === 'Delivered'): ?>
                    <span class="text-green-600 text-sm">
                        <i class="fas fa-check-circle mr-1"></i>Delivered on <?php echo date('M j, Y', strtotime($order['created_at'] . ' + 7 days')); ?>
                    </span>
                <?php endif; ?>
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Order Summary -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Order Items -->
                <div class="bg-white rounded-2xl shadow-lg p-6 animate fade-in">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-shopping-bag mr-3 text-amber-600"></i>Order Items
                    </h3>
                    <div class="space-y-4">
                        <?php if ($order_items): ?>
                            <?php foreach ($order_items as $item): ?>
                                <div class="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
                                    <?php
                                    // Derive main image from images and main_image_index
                                    $item_images = explode(',', $item['images'] ?? '');
                                    $item_main_index = (int)($item['main_image_index'] ?? 0);
                                    $item_main_image = 'no-image.jpg';
                                    if (!empty($item_images)) {
                                        $chosen = trim($item_images[$item_main_index] ?? ($item_images[0] ?? ''));
                                        if ($chosen) {
                                            $item_main_image = $chosen;
                                        }
                                    }
                                    ?>
                                    <img src="uploads/images/<?php echo htmlspecialchars($item_main_image); ?>"
                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                         class="w-16 h-16 object-cover rounded-lg"
                                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2RkZCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTAiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5ObyBJbWFnZTwvdGV4dD48L3N2Zz4='">
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                        <p class="text-sm text-gray-600">Quantity: <?php echo $item['quantity']; ?></p>
                                        <p class="text-sm font-medium text-amber-600">$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></p>
                                    </div>

                                    <!-- Review Section -->
                                    <?php if ($order['status'] === 'Delivered'): ?>
                                        <?php
                                        $has_review = isset($existing_reviews[$item['product_id']]);
                                        $existing_review = $has_review ? $existing_reviews[$item['product_id']][0] : null;
                                        ?>
                                        <div class="ml-auto">
                                            <?php if ($has_review): ?>
                                                <div class="text-center">
                                                    <div class="flex items-center mb-2">
                                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                                            <i class="fas fa-star <?php echo $i <= $existing_review['rating'] ? 'text-yellow-400' : 'text-gray-300'; ?> text-sm"></i>
                                                        <?php endfor; ?>
                                                    </div>
                                                    <button onclick="toggleReviewForm(<?php echo $item['product_id']; ?>)"
                                                            class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200">
                                                        Edit Review
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <button onclick="toggleReviewForm(<?php echo $item['product_id']; ?>)"
                                                        class="text-sm bg-amber-100 text-amber-700 px-3 py-1 rounded hover:bg-amber-200">
                                                    Write Review
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Review Form (Initially Hidden) -->
                                <div id="review-form-<?php echo $item['product_id']; ?>" class="hidden p-4 bg-blue-50 rounded-lg border-l-4 border-blue-400">
                                    <h5 class="font-semibold text-gray-800 mb-3">Write a Review for <?php echo htmlspecialchars($item['product_name']); ?></h5>
                                    <form method="POST">
                                        <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                        <div class="mb-3">
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Rating</label>
                                            <div class="review-stars">
                                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                                    <input type="radio" id="star<?php echo $i; ?>-<?php echo $item['product_id']; ?>"
                                                           name="rating" value="<?php echo $i; ?>"
                                                           <?php echo ($existing_review && $existing_review['rating'] == $i) ? 'checked' : ''; ?>>
                                                    <label for="star<?php echo $i; ?>-<?php echo $item['product_id']; ?>">★</label>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="review-text-<?php echo $item['product_id']; ?>" class="block text-sm font-medium text-gray-700 mb-2">Your Review</label>
                                            <textarea name="review_text" id="review-text-<?php echo $item['product_id']; ?>"
                                                      rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                                      placeholder="Share your experience with this product..."><?php echo htmlspecialchars($existing_review['review_text'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="flex gap-2">
                                            <button type="submit" name="submit_review" class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition-colors">
                                                <?php echo $has_review ? 'Update Review' : 'Submit Review'; ?>
                                            </button>
                                            <button type="button" onclick="toggleReviewForm(<?php echo $item['product_id']; ?>)"
                                                    class="bg-gray-300 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-400 transition-colors">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Timeline -->
                <div class="bg-white rounded-2xl shadow-lg p-6 animate fade-in">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-clock mr-3 text-amber-600"></i>Order Timeline
                    </h3>
                    <div class="space-y-4">
                        <?php
                        $timeline = [
                            'Pending' => ['icon' => 'fa-hourglass-start', 'color' => 'yellow', 'time' => 'Order placed'],
                            'Processing' => ['icon' => 'fa-sync', 'color' => 'blue', 'time' => 'Being prepared'],
                            'Shipped' => ['icon' => 'fa-shipping-fast', 'color' => 'purple', 'time' => 'Shipped'],
                            'Delivered' => ['icon' => 'fa-check', 'color' => 'green', 'time' => 'Delivered']
                        ];

                        $current_status = $order['status'];
                        foreach ($timeline as $status => $info):
                            $is_completed = false;
                            $status_order = ['Pending', 'Processing', 'Shipped', 'Delivered'];
                            if (array_search($current_status, $status_order) >= array_search($status, $status_order)) {
                                $is_completed = true;
                            }
                        ?>
                            <div class="flex items-center gap-4">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full <?php echo $is_completed ? 'bg-' . $info['color'] . '-500' : 'bg-gray-200'; ?> flex items-center justify-center">
                                    <i class="fas <?php echo $info['icon']; ?> text-white text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium text-gray-800"><?php echo $status; ?></p>
                                    <p class="text-sm text-gray-600"><?php echo $info['time']; ?></p>
                                </div>
                                <?php if ($status === $current_status): ?>
                                    <span class="text-xs bg-amber-100 text-amber-800 px-2 py-1 rounded-full">Current</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Order Details Sidebar -->
            <div class="space-y-6">
                <!-- Order Summary -->
                <div class="bg-white rounded-2xl shadow-lg p-6 animate fade-in">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Order Summary</h3>
                    <div class="space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="font-medium">$<?php echo number_format($order['total'], 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Shipping:</span>
                            <span class="font-medium text-green-600">Free</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax:</span>
                            <span class="font-medium">$0.00</span>
                        </div>
                        <div class="border-t pt-3">
                            <div class="flex justify-between">
                                <span class="font-semibold text-gray-800">Total:</span>
                                <span class="font-bold text-amber-600 text-lg">$<?php echo number_format($order['total'], 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shipping Information -->
                <div class="bg-white rounded-2xl shadow-lg p-6 animate fade-in">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-truck mr-3 text-amber-600"></i>Shipping Address
                    </h3>
                    <div class="text-gray-600">
                        <p class="font-medium text-gray-800"><?php echo htmlspecialchars($order['customer_name']); ?></p>
                        <p><?php echo htmlspecialchars($order['customer_address']); ?></p>
                        <p><?php echo htmlspecialchars($order['customer_city']); ?>, <?php echo htmlspecialchars($order['customer_country']); ?></p>
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="bg-white rounded-2xl shadow-lg p-6 animate fade-in">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-credit-card mr-3 text-amber-600"></i>Payment Method
                    </h3>
                    <div class="text-gray-600">
                        <p><?php echo htmlspecialchars($order['payment_method']); ?></p>
                        <?php if (!empty($order['payment_proof'])): ?>
                            <p class="text-green-600 text-sm mt-2">
                                <i class="fas fa-check-circle mr-1"></i>Payment proof uploaded
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white rounded-2xl shadow-lg p-6 animate fade-in">
                    <h3 class="text-xl font-semibold text-gray-800 mb-4">Quick Actions</h3>
                    <div class="space-y-3">
                        <?php if ($order['status'] === 'Delivered'): ?>
                            <form method="POST" action="orders.php" class="block">
                                <input type="hidden" name="action" value="reorder">
                                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                <button type="submit" class="w-full bg-green-600 text-white py-3 px-4 rounded-lg hover:bg-green-700 transition-colors flex items-center justify-center">
                                    <i class="fas fa-redo mr-2"></i>Buy Again
                                </button>
                            </form>
                        <?php endif; ?>

                        <a href="orders.php" class="block w-full bg-gray-100 text-gray-700 py-3 px-4 rounded-lg hover:bg-gray-200 transition-colors text-center">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Orders
                        </a>

                        <?php if ($order['status'] === 'Pending'): ?>
                            <form method="POST" action="orders.php" class="block" onsubmit="return confirm('Are you sure you want to cancel this order?')">
                                <input type="hidden" name="action" value="cancel_order">
                                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                                <button type="submit" class="w-full bg-red-600 text-white py-3 px-4 rounded-lg hover:bg-red-700 transition-colors">
                                    <i class="fas fa-times mr-2"></i>Cancel Order
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        function toggleReviewForm(productId) {
            const form = document.getElementById(`review-form-${productId}`);
            form.classList.toggle('hidden');
        }

        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.bg-green-100, .bg-red-100');
            messages.forEach(msg => {
                msg.style.opacity = '0';
                msg.style.transform = 'translateY(-10px)';
                setTimeout(() => msg.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>
