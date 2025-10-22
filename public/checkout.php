<?php
require_once __DIR__ . '/../app/core/db.php';

// Require user login
require_login();

// Initialize variables
$cart_items = [];
$subtotal = 0;
$tax = 0;
$total = 0;
$errors = [];

// Get cart items from session (source of truth used across the site)
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (!empty($_SESSION['cart'])) {
    try {
        $product_ids = array_keys($_SESSION['cart']);
        $placeholders = implode(',', array_fill(0, count($product_ids), '?'));

        $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
        $stmt->execute($product_ids);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $cart_items = [];

        foreach ($products as $product) {
            $quantity = (int)($_SESSION['cart'][$product['id']] ?? 0);
            if ($quantity <= 0) { continue; }

            $item_total = ((float)$product['price']) * $quantity;
            $subtotal += $item_total;

            // Derive main image filename similar to cart page
            $images = explode(',', $product['images'] ?? '');
            $main_index = (int)($product['main_image_index'] ?? 0);
            $chosen = '';
            if (!empty($images)) {
                $chosen = trim($images[$main_index] ?? ($images[0] ?? ''));
            }

            $cart_items[] = [
                'product' => [
                    'id' => $product['id'],
                    'name' => $product['name'],
                    'price' => (float)$product['price'],
                    // Template expects just the filename; it prepends uploads/images/
                    'image' => $chosen ?: 'default.jpg',
                    'stock_quantity' => (int)($product['stock_quantity'] ?? $product['stock'] ?? 0)
                ],
                'quantity' => $quantity,
                'item_total' => $item_total
            ];
        }
    } catch (PDOException $e) {
        $errors[] = 'Error loading cart items. Please try again.';
        error_log('Cart error (session): ' . $e->getMessage());
        $cart_items = [];
        $subtotal = 0;
    }
}

// Calculate totals
$shipping = 0; // Free shipping
$tax = $subtotal * 0.10; // 10% tax rate
$total = $subtotal + $tax + $shipping; // Calculate total
$total = $subtotal + $shipping + $tax;

// Handle checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    $customer_name = sanitize_input($_POST['customer_name'] ?? '');
    $customer_email = sanitize_input($_POST['customer_email'] ?? '');
    $customer_phone = sanitize_input($_POST['customer_phone'] ?? '');
    $customer_address = sanitize_input($_POST['customer_address'] ?? '');
    $customer_city = sanitize_input($_POST['customer_city'] ?? '');
    $customer_country = sanitize_input($_POST['customer_country'] ?? '');
    $payment_method = sanitize_input($_POST['payment_method'] ?? '');
    $order_notes = sanitize_input($_POST['order_notes'] ?? '');

    // Validate required fields
    if (empty($customer_name)) $errors[] = "Customer name is required";
    if (empty($customer_email)) $errors[] = "Email is required";
    if (empty($customer_phone)) $errors[] = "Phone number is required";
    if (empty($customer_address)) $errors[] = "Address is required";
    if (empty($customer_city)) $errors[] = "City is required";
    if (empty($customer_country)) $errors[] = "Country is required";
    if (empty($payment_method)) $errors[] = "Payment method is required";
    if (empty($cart_items)) $errors[] = "Your cart is empty";

    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Create order (excluding order_notes to match current schema)
            $stmt = $pdo->prepare("\n                INSERT INTO orders (user_id, customer_name, customer_email, customer_phone, \n                                  customer_address, customer_city, customer_country, \n                                  payment_method, total, status)\n                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)\n            ");
            
            // Set initial status based on payment method
            $status = ($payment_method === 'Cash on Delivery') ? 'Pending Payment' : 'Processing';
            
            $stmt->execute([
                $_SESSION['user_id'],
                $customer_name,
                $customer_email,
                $customer_phone,
                $customer_address,
                $customer_city,
                $customer_country,
                $payment_method,
                $total,
                $status
            ]);

            $order_id = $pdo->lastInsertId();

            // Insert order items
            $stmt = $pdo->prepare("
                INSERT INTO order_items (order_id, product_id, quantity, price)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($cart_items as $item) {
                $stmt->execute([
                    $order_id,
                    $item['product']['id'],
                    $item['quantity'],
                    $item['product']['price']
                ]);
            }

            // Update product quantities based on ordered items (use `stock` column as elsewhere)
            $updateStmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            foreach ($cart_items as $item) {
                $updateStmt->execute([$item['quantity'], $item['product']['id']]);
            }

            // Clear the session cart only if order was successful
            $_SESSION['cart'] = [];
            
            // Log user activity for order placement
            log_user_activity($_SESSION['user_id'], 'order_placed', 'Order #' . $order_id . ' placed - Total: $' . number_format($total, 2));
            
            // Commit transaction
            $pdo->commit();
            
            // Store order details in session for success page
            $_SESSION['last_order'] = [
                'order_id' => $order_id,
                'total' => $total,
                'payment_method' => $payment_method,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email
            ];
            
            // Redirect to success page
            header("Location: order_success.php");
            exit();

        } catch (PDOException $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = "Error processing your order: " . $e->getMessage();
            error_log("Order error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    }
}

// Pre-fill form with user data if available
$user_data = [];
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name, email, phone, address, city, country FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("User data error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - Wigtopia</title>
    <meta name="description" content="Complete your purchase securely.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="assets/css/smooth-animations.css" rel="stylesheet">
    <link href="assets/css/dark-mode.css" rel="stylesheet">
    <link href="assets/css/loading-skeleton.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-step {
            display: none;
        }
        .form-step.active {
            display: block;
        }
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        /* Advanced Progress Steps Animation */
        .progress-container {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }
        
        .step-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .step-indicator {
            position: relative;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            font-weight: bold;
            z-index: 2;
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .step-active {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
            animation: pulse-ring 2s infinite, rotate-gradient 3s linear infinite;
        }
        
        .step-active::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            animation: pulse-ring 2s infinite;
            z-index: -1;
        }
        
        .step-active::after {
            content: '✓';
            position: absolute;
            font-size: 1.5rem;
            color: white;
            opacity: 0;
            animation: checkmark-pop 0.6s ease-out 0.3s forwards;
        }
        
        .step-completed {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
            transform: scale(1.1);
        }
        
        .step-completed::after {
            content: '✓';
            position: absolute;
            font-size: 1.5rem;
            color: white;
            animation: checkmark-bounce 0.6s ease-out;
        }
        
        .step-inactive {
            background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
            color: #6b7280;
            transform: scale(0.9);
        }
        
        .step-line {
            position: relative;
            width: 80px;
            height: 4px;
            background: #e5e7eb;
            margin: 0 1rem;
            border-radius: 2px;
            overflow: hidden;
        }
        
        .step-line-active {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            background: linear-gradient(90deg, #f59e0b 0%, #d97706 50%, #f59e0b 100%);
            background-size: 200% 100%;
            animation: line-flow 2s linear infinite;
            box-shadow: 0 0 10px rgba(245, 158, 11, 0.5);
        }
        
        .step-line-completed {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, #10b981 0%, #059669 100%);
            animation: line-complete 0.8s ease-out forwards;
        }
        
        .step-label {
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.875rem;
            font-weight: 600;
            white-space: nowrap;
            transition: all 0.3s ease;
        }
        
        .step-active .step-label {
            color: #f59e0b;
            font-size: 1rem;
        }
        
        .step-completed .step-label {
            color: #10b981;
        }
        
        .step-inactive .step-label {
            color: #9ca3af;
        }
        
        /* Keyframe Animations */
        @keyframes pulse-ring {
            0% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
            }
            50% {
                box-shadow: 0 0 0 15px rgba(245, 158, 11, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(245, 158, 11, 0);
            }
        }
        
        @keyframes rotate-gradient {
            0% {
                filter: hue-rotate(0deg);
            }
            100% {
                filter: hue-rotate(10deg);
            }
        }
        
        @keyframes checkmark-pop {
            0% {
                opacity: 0;
                transform: scale(0) rotate(-45deg);
            }
            50% {
                opacity: 1;
                transform: scale(1.2) rotate(0deg);
            }
            100% {
                opacity: 1;
                transform: scale(1) rotate(0deg);
            }
        }
        
        @keyframes checkmark-bounce {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.2);
            }
        }
        
        @keyframes line-flow {
            0% {
                background-position: 200% 0;
            }
            100% {
                background-position: -200% 0;
            }
        }
        
        @keyframes line-complete {
            0% {
                width: 0;
            }
            100% {
                width: 100%;
            }
        }
        
        /* Responsive Design */
        @media (max-width: 640px) {
            .step-indicator {
                width: 50px;
                height: 50px;
                font-size: 1rem;
            }
            .step-line {
                width: 50px;
                margin: 0 0.5rem;
            }
            .step-label {
                font-size: 0.75rem;
                bottom: -25px;
            }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'header.php'; ?>

    <main class="container mx-auto px-4 py-8 md:py-12">
        <!-- Header -->
        <div class="mb-8 animate fade-in">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">Checkout</h1>
                    <p class="text-gray-600">Complete your purchase securely</p>
                </div>
                <a href="cart.php" class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition-all duration-300 font-medium">
                    ← Back to Cart
                </a>
            </div>
        <!-- Advanced Progress Steps -->
        <div class="progress-container">
            <div class="step-wrapper">
                <!-- Step 1: Cart Review -->
                <div class="relative">
                    <div class="step-indicator step-active">
                        <span>1</span>
                    </div>
                    <div class="step-label">Cart Review</div>
                </div>
                
                <!-- Line 1 -->
                <div class="step-line">
                    <div class="step-line-active" style="width: 100%;"></div>
                </div>
                
                <!-- Step 2: Information -->
                <div class="relative">
                    <div class="step-indicator step-active">
                        <span>2</span>
                    </div>
                    <div class="step-label">Information</div>
                </div>
                
                <!-- Line 2 -->
                <div class="step-line">
                    <div class="step-line-active" style="width: 0%;"></div>
                </div>
                
                <!-- Step 3: Payment -->
                <div class="relative">
                    <div class="step-indicator step-inactive">
                        <span>3</span>
                    </div>
                    <div class="step-label">Payment</div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded">
            <div class="flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i>
                <div>
                    <h3 class="font-semibold">Please fix the following issues:</h3>
                    <ul class="list-disc list-inside mt-1">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" class="space-y-8">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Column: Shipping Information -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Customer Information -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-user-circle text-amber-600 mr-2"></i>
                        Contact Information
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="customer_name" class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                            <input type="text" id="customer_name" name="customer_name" required
                                   value="<?php echo htmlspecialchars($_POST['customer_name'] ?? $user_data['name'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                        </div>
                        <div>
                            <label for="customer_email" class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                            <input type="email" id="customer_email" name="customer_email" required
                                   value="<?php echo htmlspecialchars($_POST['customer_email'] ?? $user_data['email'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                        </div>
                        <div class="md:col-span-2">
                            <label for="customer_phone" class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
                            <input type="tel" id="customer_phone" name="customer_phone" required
                                   value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? $user_data['phone'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                        </div>
                    </div>
                </div>

                <!-- Shipping Address -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-truck text-amber-600 mr-2"></i>
                        Shipping Address
                    </h2>
                    <div class="space-y-4">
                        <div>
                            <label for="customer_address" class="block text-sm font-medium text-gray-700 mb-1">Street Address *</label>
                            <input type="text" id="customer_address" name="customer_address" required
                                   value="<?php echo htmlspecialchars($_POST['customer_address'] ?? $user_data['address'] ?? ''); ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="customer_city" class="block text-sm font-medium text-gray-700 mb-1">City *</label>
                                <input type="text" id="customer_city" name="customer_city" required
                                       value="<?php echo htmlspecialchars($_POST['customer_city'] ?? $user_data['city'] ?? ''); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                            </div>
                            <div>
                                <label for="customer_country" class="block text-sm font-medium text-gray-700 mb-1">Country *</label>
                                <select id="customer_country" name="customer_country" required
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                    <option value="">Select Country</option>
                                    <option value="US" <?php echo (($_POST['customer_country'] ?? $user_data['country'] ?? '') === 'US') ? 'selected' : ''; ?>>United States</option>
                                    <option value="CA" <?php echo (($_POST['customer_country'] ?? $user_data['country'] ?? '') === 'CA') ? 'selected' : ''; ?>>Canada</option>
                                    <option value="UK" <?php echo (($_POST['customer_country'] ?? $user_data['country'] ?? '') === 'UK') ? 'selected' : ''; ?>>United Kingdom</option>
                                    <option value="AU" <?php echo (($_POST['customer_country'] ?? $user_data['country'] ?? '') === 'AU') ? 'selected' : ''; ?>>Australia</option>
                                    <option value="Other" <?php echo (empty(($_POST['customer_country'] ?? $user_data['country'] ?? '')) ? 'selected' : ''); ?>>Other</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Method -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-credit-card text-amber-600 mr-2"></i>
                        Payment Method
                    </h2>
                    <div class="space-y-4">
                        <div class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-amber-500 transition-colors">
                            <input type="radio" id="credit_card" name="payment_method" value="Credit Card" 
                                   class="h-4 w-4 text-amber-600 focus:ring-amber-500" 
                                   <?php echo (($_POST['payment_method'] ?? '') === 'Credit Card') ? 'checked' : 'checked'; ?> required>
                            <label for="credit_card" class="ml-3 block text-sm font-medium text-gray-700">
                                <div class="flex items-center">
                                    <i class="fas fa-credit-card text-gray-600 mr-2"></i>
                                    Credit/Debit Card
                                </div>
                            </label>
                        </div>

                        <div class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-amber-500 transition-colors">
                            <input type="radio" id="paypal" name="payment_method" value="PayPal" 
                                   class="h-4 w-4 text-amber-600 focus:ring-amber-500"
                                   <?php echo (($_POST['payment_method'] ?? '') === 'PayPal') ? 'checked' : ''; ?>>
                            <label for="paypal" class="ml-3 block text-sm font-medium text-gray-700">
                                <div class="flex items-center">
                                    <i class="fab fa-paypal text-blue-500 mr-2"></i>
                                    PayPal
                                </div>
                            </label>
                        </div>

                        <div class="flex items-center p-4 border border-gray-200 rounded-lg hover:border-amber-500 transition-colors">
                            <input type="radio" id="cod" name="payment_method" value="Cash on Delivery" 
                                   class="h-4 w-4 text-amber-600 focus:ring-amber-500"
                                   <?php echo (($_POST['payment_method'] ?? '') === 'Cash on Delivery') ? 'checked' : ''; ?>>
                            <label for="cod" class="ml-3 block text-sm font-medium text-gray-700">
                                <div class="flex items-center">
                                    <i class="fas fa-money-bill-wave text-green-500 mr-2"></i>
                                    Cash on Delivery
                                </div>
                            </label>
                        </div>

                        <!-- Card Details (shown when credit card is selected) -->
                        <div id="card-details" class="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Card Number</label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <i class="far fa-credit-card text-gray-400"></i>
                                        </div>
                                        <input type="text" placeholder="1234 5678 9012 3456" 
                                               class="pl-10 w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Expiry Date</label>
                                        <input type="text" placeholder="MM/YY" 
                                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">CVV</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <i class="fas fa-question-circle text-gray-400" title="3 digits on back of card"></i>
                                            </div>
                                            <input type="text" placeholder="123" 
                                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Notes -->
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center">
                        <i class="fas fa-sticky-note text-amber-600 mr-2"></i>
                        Order Notes (Optional)
                    </h2>
                    <textarea name="order_notes" rows="3" placeholder="Special instructions for delivery, gift messages, etc."
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"><?php echo htmlspecialchars($_POST['order_notes'] ?? ''); ?></textarea>
                </div>
            </div>

            <!-- Right Column: Order Summary -->
            <div class="lg:sticky lg:top-8 h-fit">
                <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Order Summary</h2>
                    
                    <!-- Cart Items -->
                    <div class="space-y-4 mb-6 max-h-96 overflow-y-auto">
                        <?php if (!empty($cart_items)): ?>
                            <?php foreach ($cart_items as $item): ?>
                                <div class="flex items-start gap-4 p-3 bg-gray-50 rounded-lg">
                                    <img src="uploads/images/<?php echo htmlspecialchars($item['product']['image'] ?? 'default.jpg'); ?>" 
                                         alt="<?php echo htmlspecialchars($item['product']['name']); ?>"
                                         class="w-16 h-16 object-cover rounded-lg">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($item['product']['name']); ?></h3>
                                        <p class="text-sm text-gray-500">Qty: <?php echo $item['quantity']; ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-amber-600">$<?php echo number_format($item['item_total'], 2); ?></p>
                                        <p class="text-xs text-gray-500">$<?php echo number_format($item['product']['price'], 2); ?> each</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-gray-500 py-4">Your cart is empty</p>
                        <?php endif; ?>
                    </div>

                    <!-- Order Totals -->
                    <div class="border-t border-gray-200 pt-4 space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-medium">$<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Shipping</span>
                            <span class="font-medium text-green-600">Free</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax (10%)</span>
                            <span class="font-medium">$<?php echo number_format($tax, 2); ?></span>
                        </div>
                        <div class="border-t border-gray-200 pt-3 mt-3">
                            <div class="flex justify-between">
                                <span class="text-lg font-semibold text-gray-900">Total</span>
                                <span class="text-lg font-bold text-amber-600">$<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Terms and Place Order -->
                    <div class="mt-6 space-y-4">
                        <div class="flex items-start">
                            <input type="checkbox" id="terms" name="terms" required
                                   class="mt-1 h-4 w-4 text-amber-600 focus:ring-amber-500 border-gray-300 rounded">
                            <label for="terms" class="ml-2 block text-sm text-gray-600">
                                I agree to the <a href="#" class="text-amber-600 hover:text-amber-700">Terms of Service</a>
                                and <a href="#" class="text-amber-600 hover:text-amber-700">Privacy Policy</a>
                            </label>
                        </div>

                        <button type="submit" name="place_order"
                                class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 disabled:opacity-50 disabled:cursor-not-allowed"
                                <?php echo empty($cart_items) ? 'disabled' : ''; ?>>
                            <i class="fas fa-lock mr-2"></i> Place Order - $<?php echo number_format($total, 2); ?>
                        </button>

                        <p class="text-xs text-center text-gray-500 mt-2">
                            <i class="fas fa-shield-alt text-gray-400 mr-1"></i>
                            Secure SSL encryption
                        </p>
                    </div>
                </div>

                <!-- Trust Badges -->
                <div class="mt-4 grid grid-cols-3 gap-2">
                    <div class="bg-white p-3 rounded-lg border border-gray-100 flex flex-col items-center justify-center">
                        <i class="fas fa-truck text-amber-500 text-xl mb-1"></i>
                        <span class="text-xs text-center">Free Shipping</span>
                    </div>
                    <div class="bg-white p-3 rounded-lg border border-gray-100 flex flex-col items-center justify-center">
                        <i class="fas fa-undo text-amber-500 text-xl mb-1"></i>
                        <span class="text-xs text-center">Easy Returns</span>
                    </div>
                    <div class="bg-white p-3 rounded-lg border border-gray-100 flex flex-col items-center justify-center">
                        <i class="fas fa-lock text-amber-500 text-xl mb-1"></i>
                        <span class="text-xs text-center">Secure Checkout</span>
                    </div>
                </div>
            </div>
        </div>
    </form>
</main>

<?php include 'footer.php'; ?>

<script>
    // Show/hide card details based on payment method
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const cardDetails = document.getElementById('card-details');

    function toggleCardDetails() {
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
        cardDetails.style.display = selectedMethod === 'Credit Card' ? 'block' : 'none';
        
        // Update the order button text based on payment method
        const orderButton = document.querySelector('button[name="place_order"]');
        if (orderButton) {
            if (selectedMethod === 'Cash on Delivery') {
                orderButton.innerHTML = orderButton.innerHTML.replace('Place Order', 'Complete Order (Pay on Delivery)');
            } else {
                orderButton.innerHTML = orderButton.innerHTML.replace('Complete Order \(Pay on Delivery\)', 'Place Order');
            }
        }
    }

    paymentMethods.forEach(method => {
        method.addEventListener('change', toggleCardDetails);
    });

    // Initial check
    toggleCardDetails();

    // Form validation
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const termsCheckbox = document.getElementById('terms');
            if (!termsCheckbox.checked) {
                e.preventDefault();
                alert('Please accept the Terms of Service and Privacy Policy');
                termsCheckbox.focus();
                return false;
            }
        });
    }

    // Format credit card number
    const cardNumberInput = document.querySelector('input[placeholder="1234 5678 9012 3456"]');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s+/g, '').replace(/[^0-9]/g, '');
            value = value.replace(/(\d{4})(?=\d)/g, '$1 ');
            e.target.value = value.trim();
        });
    }

    // Format expiry date
    const expiryInput = document.querySelector('input[placeholder="MM/YY"]');
    if (expiryInput) {
        expiryInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 2) {
                value = value.substring(0, 2) + '/' + value.substring(2, 4);
            }
            e.target.value = value;
        });
    }

    // Format CVV
    const cvvInput = document.querySelector('input[placeholder="123"]');
    if (cvvInput) {
        cvvInput.addEventListener('input', function(e) {
            e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
        });
    }
</script>
<script src="assets/js/smooth-loader.js"></script>
<script src="assets/js/loading-skeleton.js"></script>
<script src="assets/js/dark-mode.js"></script>
</body>
</html>
