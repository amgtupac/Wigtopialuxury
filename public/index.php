<?php
require_once '../app/core/db.php';

// Get featured products (where featured = 1, latest 8)
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE featured = 1 ORDER BY created_at DESC LIMIT 8");
    $stmt->execute();
    $featured_products = $stmt->fetchAll();
} catch (PDOException $e) {
    $featured_products = [];
}

// Get product categories for quick links
try {
    $stmt = $pdo->prepare("SELECT * FROM categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elegant Wigs - Premium Wigs & Hair Extensions</title>
    <meta name="description" content="Discover premium wigs, hair extensions, and accessories at Elegant Wigs. Quality human hair and synthetic options for every style.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="assets/css/smooth-animations.css" rel="stylesheet">
    <link href="assets/css/dark-mode.css" rel="stylesheet">
    <link href="assets/css/loading-skeleton.css" rel="stylesheet">
    <style>
        /* Custom Tailwind styles for enhanced hover effects and animations */
        .hover-scale {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .hover-scale:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .fade-in {
            animation: fadeIn 1s ease-in-out;
        }
        @keyframes fadeIn {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'header.php'; ?>
    
    <main>
        <!-- Hero Section -->
        <section class="relative bg-gradient-to-r from-amber-900/80 to-amber-800/80 text-white py-16 px-4 sm:px-6 lg:px-8 rounded-3xl mx-4 mt-24 mb-6 overflow-hidden fade-in">
            <div class="container mx-auto text-center">
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight mb-4">WIGTOPIA LUXURY</h1>
                <p class="text-xl sm:text-2xl lg:text-3xl mb-6">Luxury Redefined</p>
                <a href="products.php" class="inline-block bg-gradient-to-r from-amber-400 to-amber-600 text-amber-900 font-bold py-3 px-8 rounded-full text-lg uppercase tracking-wide shadow-lg hover:bg-amber-500 transition-all duration-300">Shop Now</a>
                <div class="mt-8">
                    <img src="assets/images/index img.jpg" alt="Wigtopia Luxury Collection" class="mx-auto rounded-2xl w-full max-w-3xl h-auto">
                </div>
            </div>
        </section>
        
        <!-- Success Message with Animation -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="fixed top-6 left-1/2 -translate-x-1/2 bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-3 rounded-xl shadow-xl flex items-center gap-3 z-50 opacity-0 transform -translate-y-10 transition-all duration-500" id="successMessage">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span class="font-medium"><?php echo htmlspecialchars($_SESSION['message']); unset($_SESSION['message']); ?></span>
                <button onclick="this.parentElement.remove()" class="ml-4 text-white/80 hover:text-white transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>
            <script>
                // Show message with animation
                document.addEventListener('DOMContentLoaded', function() {
                    const message = document.getElementById('successMessage');
                    if (message) {
                        // Trigger reflow
                        void message.offsetWidth;
                        // Add show class to trigger animation
                        message.classList.add('opacity-100', 'translate-y-0');
                        
                        // Auto-remove after 5 seconds
                        setTimeout(() => {
                            message.classList.remove('opacity-100', 'translate-y-0');
                            message.classList.add('opacity-0', '-translate-y-10');
                            setTimeout(() => message.remove(), 500);
                        }, 5000);
                    }
                });
            </script>
        <?php endif; ?>
        
        <!-- Categories Section -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-gradient-to-r from-amber-50/50 to-amber-100/50 rounded-3xl mx-4 mb-6">
            <div class="container mx-auto">
                <h2 class="text-3xl sm:text-4xl font-bold text-gray-800 text-center mb-8">Shop by Category</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php foreach ($categories as $category): ?>
                        <div class="category-card bg-white p-6 rounded-2xl shadow-md hover-scale">
                            <div class="text-4xl mb-4"><?php echo htmlspecialchars($category['icon']); ?></div>
                            <h3 class="text-xl font-semibold text-gray-800 mb-2"><?php echo htmlspecialchars($category['name']); ?></h3>
                            <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($category['description']); ?></p>
                            <a href="products.php?category=<?php echo urlencode($category['name']); ?>" class="inline-block bg-amber-600 text-white py-2 px-4 rounded-full hover:bg-amber-700 transition-all duration-300">Shop <?php echo htmlspecialchars($category['name']); ?></a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        
        <!-- Featured Products Section -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-gradient-to-r from-amber-100/70 to-amber-200/70 rounded-3xl mx-4 mb-6">
            <div class="container mx-auto">
                <div class="text-center mb-10">
                    <h2 class="text-3xl sm:text-4xl font-bold text-gray-800 mb-2">Featured Products</h2>
                    <p class="text-gray-600">Discover our latest and most popular items</p>
                </div>

                <?php if (empty($featured_products)): ?>
                    <div class="text-center py-12">
                        <p class="text-gray-600 mb-4">No products available at the moment.</p>
                        <a href="products.php" class="inline-block bg-amber-600 text-white py-2 px-6 rounded-full hover:bg-amber-700 transition-all duration-300">Browse All Products</a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        <?php foreach ($featured_products as $product): ?>
                            <?php
                            $images = explode(',', $product['images'] ?? '');
                            $main_index = (int)($product['main_image_index'] ?? 0);
                            $main_image = !empty($images[$main_index]) ? 'uploads/images/' . trim($images[$main_index]) : (!empty($images[0]) ? 'uploads/images/' . trim($images[0]) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg==');
                            ?>
                            <div class="product-card bg-white p-6 rounded-2xl shadow-md hover-scale">
                                <div class="relative">
                                    <?php if ((bool)($product['featured'] ?? 0)): ?>
                                        <span class="absolute top-2 left-2 bg-amber-500 text-white text-xs font-bold px-2 py-1 rounded-full">Featured</span>
                                    <?php endif; ?>
                                    <img src="<?php echo htmlspecialchars($main_image); ?>"
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         class="w-full h-48 object-cover rounded-xl mb-4"
                                         onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg=='">
                                </div>
                                <div class="product-info">
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($product['category']); ?></div>
                                    <h3 class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <div class="text-xl font-bold text-amber-600">$<?php echo number_format($product['price'], 2); ?></div>
                                    <div class="mt-4 flex space-x-2">
                                        <a href="product-details.php?id=<?php echo $product['id']; ?>" class="flex-1 bg-amber-600 text-white py-2 px-4 rounded-full text-center hover:bg-amber-700 transition-all duration-300">View Details</a>
                                        <button class="flex-1 bg-white border border-amber-600 text-amber-600 py-2 px-4 rounded-full hover:bg-amber-50 transition-all duration-300 add-to-cart" data-product-id="<?php echo $product['id']; ?>">Add to Cart</button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-10">
                        <a href="products.php" class="inline-block bg-gray-200 text-gray-800 py-2 px-6 rounded-full hover:bg-gray-300 transition-all duration-300">View All Products</a>
                    </div>
                <?php endif; ?>
            </div>
        </section>
        
        <!-- Features Section -->
        <section class="py-16 px-4 sm:px-6 lg:px-8 bg-gradient-to-r from-amber-900 to-amber-800/70 text-white rounded-3xl mx-4 mb-6">
            <div class="container mx-auto">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 text-center">
                    <div class="stat-card p-6 rounded-2xl bg-white/10 backdrop-blur-md">
                        <div class="text-4xl mb-4">🚚</div>
                        <h3 class="text-xl font-semibold mb-2">Free Shipping</h3>
                        <p>Free delivery on orders over $100</p>
                    </div>
                    <div class="stat-card p-6 rounded-2xl bg-white/10 backdrop-blur-md">
                        <div class="text-4xl mb-4">⭐</div>
                        <h3 class="text-xl font-semibold mb-2">Premium Quality</h3>
                        <p>100% human hair and high-grade synthetic options</p>
                    </div>
                    <div class="stat-card p-6 rounded-2xl bg-white/10 backdrop-blur-md">
                        <div class="text-4xl mb-4">💎</div>
                        <h3 class="text-xl font-semibold mb-2">Expert Styling</h3>
                        <p>Professional styling tips and support</p>
                    </div>
                    <div class="stat-card p-6 rounded-2xl bg-white/10 backdrop-blur-md">
                        <div class="text-4xl mb-4">🔄</div>
                        <h3 class="text-xl font-semibold mb-2">Easy Returns</h3>
                        <p>30-day return policy for your peace of mind</p>
                    </div>
                </div>
            </div>
        </section>
    </main>
    
    <?php include 'footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/smooth-loader.js"></script>
    <script src="assets/js/loading-skeleton.js"></script>
    <script src="assets/js/dark-mode.js"></script>
</body>
</html>