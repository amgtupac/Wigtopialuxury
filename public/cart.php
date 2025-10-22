<?php
require_once '../app/core/db.php';

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart_items = [];
$subtotal = 0;

// Get cart products
if (!empty($_SESSION['cart'])) {
    try {
        $product_ids = array_keys($_SESSION['cart']);
        $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
        
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
        $stmt->execute($product_ids);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($products as $product) {
            $quantity = $_SESSION['cart'][$product['id']];
            $item_total = $product['price'] * $quantity;
            $subtotal += $item_total;
            
            $product['quantity'] = $quantity;
            $product['item_total'] = $item_total;
            $cart_items[] = $product;
        }
    } catch (PDOException $e) {
        $cart_items = [];
        $subtotal = 0;
    }
}

$shipping = 0; // Free shipping
$tax = 0; // No tax for now
$total = $subtotal + $shipping + $tax;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Wigtopia</title>
    <meta name="description" content="Review your cart and proceed to checkout for premium wigs and hair accessories.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="assets/css/smooth-animations.css" rel="stylesheet">
    <link href="assets/css/dark-mode.css" rel="stylesheet">
    <link href="assets/css/loading-skeleton.css" rel="stylesheet">
    <style>
        .hover-scale { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .hover-scale:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .fade-in { animation: fadeIn 0.6s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .quantity-btn { transition: background-color 0.3s ease; }
        .quantity-btn:hover { background-color: #f5f5f5; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>
    
    <main class="py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto bg-gradient-to-r from-amber-50/50 to-amber-100/50 rounded-3xl p-6 sm:p-8 shadow-lg fade-in">
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-gray-800 mb-6">Shopping Cart</h1>
            
            <?php if (empty($cart_items)): ?>
                <!-- Empty Cart -->
                <div class="text-center py-16">
                    <div class="text-6xl mb-4">🛒</div>
                    <h2 class="text-2xl font-semibold text-gray-600 mb-2">Your cart is empty</h2>
                    <p class="text-gray-500 mb-6">Add some beautiful wigs and accessories to get started!</p>
                    <a href="products.php" class="bg-amber-600 text-white py-3 px-8 rounded-full hover:bg-amber-700 transition-all duration-300 inline-block">Continue Shopping</a>
                </div>
            <?php else: ?>
                <!-- Cart Items -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 lg:gap-8">
                    <div class="lg:col-span-2">
                        <div class="bg-white rounded-2xl shadow-md overflow-hidden">
                            <table class="w-full cart-table">
                                <thead class="bg-gray-100">
                                    <tr class="text-left text-gray-600 text-sm sm:text-base">
                                        <th class="p-4">Product</th>
                                        <th class="p-4 hidden sm:table-cell">Price</th>
                                        <th class="p-4">Quantity</th>
                                        <th class="p-4 hidden md:table-cell">Total</th>
                                        <th class="p-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart_items as $item): ?>
                                        <?php
                                        $images = explode(',', $item['images'] ?? '');
                                        $main_index = (int)($item['main_image_index'] ?? 0);
                                        $main_image = !empty($images[$main_index]) ? 'uploads/images/' . trim($images[$main_index]) : (!empty($images[0]) ? 'uploads/images/' . trim($images[0]) : '');
                                        ?>
                                        <tr class="border-b border-gray-200" data-product-id="<?php echo $item['id']; ?>">
                                            <td class="p-4">
                                                <div class="flex items-center gap-4">
                                                    <?php if ($main_image): ?>
                                                        <img src="<?php echo htmlspecialchars($main_image); ?>" 
                                                             alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                             class="w-16 h-16 sm:w-20 sm:h-20 object-cover rounded-lg"
                                                             onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2RkZCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTAiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5ObyBJbWFnZTwvdGV4dD48L3N2Zz4='">
                                                    <?php else: ?>
                                                        <div class="w-16 h-16 sm:w-20 sm:h-20 bg-gray-100 flex items-center justify-center rounded-lg">
                                                            <span class="text-gray-500 text-xs">No Image</span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <h4 class="text-base sm:text-lg font-semibold text-gray-800 mb-1">
                                                            <a href="product-details.php?id=<?php echo $item['id']; ?>" class="hover:text-amber-600 transition-colors">
                                                                <?php echo htmlspecialchars($item['name']); ?>
                                                            </a>
                                                        </h4>
                                                        <p class="text-gray-600 text-sm mb-1"><?php echo htmlspecialchars($item['category']); ?></p>
                                                        <?php if (!empty($item['color'])): ?>
                                                            <p class="text-gray-500 text-xs">Color: <?php echo htmlspecialchars($item['color']); ?></p>
                                                        <?php endif; ?>
                                                        <?php if (!empty($item['size'])): ?>
                                                            <p class="text-gray-500 text-xs">Size: <?php echo htmlspecialchars($item['size']); ?></p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-4 hidden sm:table-cell text-gray-800 font-medium">$<?php echo number_format($item['price'], 2); ?></td>
                                            <td class="p-4">
                                                <div class="flex items-center gap-2 sm:gap-3 quantity-controls">
                                                    <button class="quantity-btn bg-gray-100 text-gray-600 px-2 py-1 sm:px-3 sm:py-2 rounded-lg hover:bg-gray-200" 
                                                            data-action="decrease" 
                                                            data-product-id="<?php echo $item['id']; ?>">−</button>
                                                    <span class="quantity-value text-gray-800 font-medium"><?php echo $item['quantity']; ?></span>
                                                    <button class="quantity-btn bg-gray-100 text-gray-600 px-2 py-1 sm:px-3 sm:py-2 rounded-lg hover:bg-gray-200" 
                                                            data-action="increase" 
                                                            data-product-id="<?php echo $item['id']; ?>">+</button>
                                                </div>
                                            </td>
                                            <td class="p-4 hidden md:table-cell text-gray-800 font-medium item-total">$<?php echo number_format($item['item_total'], 2); ?></td>
                                            <td class="p-4">
                                                <button class="bg-red-500 text-white p-2 sm:p-3 rounded-lg hover:bg-red-600 transition-all duration-300 remove-from-cart" 
                                                        data-product-id="<?php echo $item['id']; ?>">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                                                        <path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/>
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            <a href="products.php" class="bg-white text-amber-600 py-2 px-4 sm:px-6 rounded-full border border-amber-600 hover:bg-amber-50 transition-all duration-300 inline-block">Continue Shopping</a>
                        </div>
                    </div>
                    
                    <!-- Cart Summary -->
                    <div class="bg-white rounded-2xl shadow-md p-4 sm:p-6">
                        <h3 class="text-lg sm:text-xl font-semibold text-gray-800 mb-4">Order Summary</h3>
                        <div class="space-y-3 text-gray-600 text-sm sm:text-base">
                            <div class="flex justify-between">
                                <span>Subtotal:</span>
                                <span class="subtotal font-medium text-gray-800">$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span>Shipping:</span>
                                <span class="text-green-600 font-medium">Free</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Tax:</span>
                                <span>$0.00</span>
                            </div>
                            <div class="flex justify-between border-t border-gray-200 pt-3 mt-3">
                                <span class="font-semibold text-gray-800">Total:</span>
                                <span class="total font-bold text-amber-600 text-lg">$<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>
                        <a href="checkout.php" class="block bg-amber-600 text-white py-3 px-6 rounded-full text-center hover:bg-amber-700 transition-all duration-300 mt-6">Proceed to Checkout</a>
                        
                        <!-- Trust Badges -->
                        <div class="mt-6 pt-4 border-t border-gray-200">
                            <h4 class="text-sm font-semibold text-gray-800 text-center mb-3">Why Shop With Us?</h4>
                            <div class="text-gray-600 text-xs sm:text-sm text-center space-y-2">
                                <p>🔒 Secure Checkout</p>
                                <p>🚚 Free Shipping Over $100</p>
                                <p>↩️ 30-Day Returns</p>
                                <p>💎 Premium Quality</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
    
    <script>
        // Function to refresh cart content
        function refreshCart() {
            // Reload the page to show updated cart contents
            location.reload();
        }

        // Override the default form submission for cart actions
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('add-to-cart') || 
                e.target.classList.contains('quantity-btn') || 
                e.target.classList.contains('remove-from-cart')) {
                e.preventDefault();
                // The main.js handles the actual cart operations and shows notifications
                // The page will refresh automatically when needed
            }
        });
    </script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/smooth-loader.js"></script>
    <script src="assets/js/loading-skeleton.js"></script>
    <script src="assets/js/dark-mode.js"></script>
</body>
</html>