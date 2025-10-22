<?php
require_once '../app/core/db.php';

// Get product ID
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    redirect('products.php', 'Invalid product ID');
}

// Get product details
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        redirect('products.php', 'Product not found');
    }
} catch (PDOException $e) {
    redirect('products.php', 'Error loading product');
}

// Get related products (same category)
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE category = ? AND id != ? ORDER BY RAND() LIMIT 4");
    $stmt->execute([$product['category'], $product_id]);
    $related_products = $stmt->fetchAll();
} catch (PDOException $e) {
    $related_products = [];
}

// Process images
$images = array_filter(array_map('trim', explode(',', $product['images'] ?? '')));
$main_index = (int)($product['main_image_index'] ?? 0);
$main_image = !empty($images[$main_index]) ? 'uploads/images/' . $images[$main_index] : (!empty($images[0]) ? 'uploads/images/' . $images[0] : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg==');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - Wigtopia</title>
    <meta name="description" content="<?php echo htmlspecialchars(substr($product['description'], 0, 160)); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="assets/css/smooth-animations.css" rel="stylesheet">
    <style>
        .hover-scale { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .hover-scale:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .fade-in { animation: fadeIn 0.6s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .thumbnail { transition: opacity 0.3s ease; }
        .thumbnail.active { opacity: 1; border: 2px solid #D4AF37; }
        .thumbnail:not(.active) { opacity: 0.6; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>
    
    <main class="py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto bg-gradient-to-r from-amber-50/50 to-amber-100/50 rounded-3xl p-6 sm:p-8 shadow-lg fade-in">
            <!-- Breadcrumb -->
            <nav class="text-sm sm:text-base text-gray-600 mb-6">
                <a href="index.php" class="text-amber-600 hover:text-amber-700">Home</a> &gt;
                <a href="products.php" class="text-amber-600 hover:text-amber-700">Products</a> &gt;
                <a href="products.php?category=<?php echo urlencode($product['category']); ?>" class="text-amber-600 hover:text-amber-700">
                    <?php echo htmlspecialchars($product['category']); ?>
                </a> &gt;
                <span class="text-gray-800"><?php echo htmlspecialchars($product['name']); ?></span>
            </nav>
            
            <!-- Product Details -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 mb-12">
                <!-- Product Images -->
                <div>
                    <?php if (!empty($images)): ?>
                        <div class="image-gallery">
                            <div class="main-image mb-4">
                                <img src="<?php echo htmlspecialchars($main_image); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="w-full h-auto max-h-[400px] object-cover rounded-2xl"
                                     id="mainImage"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjQwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxOCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg=='">
                            </div>
                            <?php if (count($images) > 1): ?>
                                <div class="flex flex-wrap gap-2 justify-center">
                                    <?php foreach ($images as $index => $image): ?>
                                        <img src="uploads/images/<?php echo htmlspecialchars($image); ?>" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?> - Image <?php echo $index + 1; ?>"
                                             class="thumbnail w-16 h-16 object-cover rounded-lg cursor-pointer <?php echo $index == 0 ? 'active' : ''; ?>"
                                             onclick="document.getElementById('mainImage').src=this.src; document.querySelectorAll('.thumbnail').forEach(img => img.classList.remove('active')); this.classList.add('active');"
                                             onerror="this.style.display='none'">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="bg-gray-100 h-64 sm:h-96 flex items-center justify-center rounded-2xl">
                            <p class="text-gray-500">No image available</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Product Information -->
                <div>
                    <div class="text-amber-600 font-medium text-sm sm:text-base mb-2">
                        <?php echo htmlspecialchars($product['category']); ?>
                    </div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-gray-800 mb-4">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h1>
                    <div class="text-2xl sm:text-3xl font-bold text-amber-600 mb-6">
                        $<?php echo number_format($product['price'], 2); ?>
                    </div>
                    
                    <!-- Product Details -->
                    <div class="bg-white p-4 sm:p-6 rounded-2xl shadow-md mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Product Details</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm sm:text-base text-gray-600">
                            <?php if (!empty($product['size'])): ?>
                                <div><span class="font-medium">Size:</span> <?php echo htmlspecialchars($product['size']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($product['color'])): ?>
                                <div><span class="font-medium">Color:</span> <?php echo htmlspecialchars($product['color']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($product['hair_type'])): ?>
                                <div><span class="font-medium">Hair Type:</span> <?php echo htmlspecialchars($product['hair_type']); ?></div>
                            <?php endif; ?>
                            <div>
                                <span class="font-medium">Stock:</span> 
                                <span class="<?php echo $product['stock'] <= 0 ? 'text-red-500' : ($product['stock'] <= 5 ? 'text-yellow-600' : 'text-green-600'); ?>">
                                    <?php if ($product['stock'] <= 0): ?>
                                        Out of Stock
                                    <?php elseif ($product['stock'] <= 5): ?>
                                        Only <?php echo $product['stock']; ?> left
                                    <?php else: ?>
                                        In Stock
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Add to Cart -->
                    <?php if ($product['stock'] > 0): ?>
                        <div class="flex flex-col sm:flex-row items-center gap-4 mb-6">
                            <div class="flex items-center gap-2">
                                <label for="quantity" class="font-medium text-gray-800">Quantity:</label>
                                <select id="quantity" class="border border-gray-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-amber-400">
                                    <?php for ($i = 1; $i <= min(10, $product['stock']); $i++): ?>
                                        <option value="<?php echo $i; ?>"><?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <button class="flex-1 bg-amber-600 text-white py-3 px-6 rounded-full hover:bg-amber-700 transition-all duration-300 add-to-cart flex items-center justify-center gap-2"
                                    data-product-id="<?php echo $product['id']; ?>">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1.003 1.003 0 0 0 20 4H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/>
                                </svg>
                                Add to Cart
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6">
                            <strong>Out of Stock</strong> - This item is currently unavailable
                        </div>
                    <?php endif; ?>
                    
                    <!-- Product Features -->
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="bg-white p-4 rounded-lg text-center shadow-md hover-scale">
                            <div class="text-2xl mb-2">🚚</div>
                            <div class="text-sm text-gray-600">Free Shipping</div>
                        </div>
                        <div class="bg-white p-4 rounded-lg text-center shadow-md hover-scale">
                            <div class="text-2xl mb-2">🔄</div>
                            <div class="text-sm text-gray-600">Easy Returns</div>
                        </div>
                        <div class="bg-white p-4 rounded-lg text-center shadow-md hover-scale">
                            <div class="text-2xl mb-2">⭐</div>
                            <div class="text-sm text-gray-600">Premium Quality</div>
                        </div>
                        <div class="bg-white p-4 rounded-lg text-center shadow-md hover-scale">
                            <div class="text-2xl mb-2">💎</div>
                            <div class="text-sm text-gray-600">Expert Support</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Product Description -->
            <div class="bg-white p-6 sm:p-8 rounded-2xl shadow-md mb-12">
                <h2 class="text-xl sm:text-2xl font-semibold text-gray-800 mb-4">Description</h2>
                <div class="text-gray-600 leading-relaxed"><?php echo nl2br(htmlspecialchars($product['description'])); ?></div>
            </div>
            
            <!-- Related Products -->
            <?php if (!empty($related_products)): ?>
                <section class="mb-12">
                    <h2 class="text-2xl sm:text-3xl font-bold text-gray-800 text-center mb-6">You May Also Like</h2>
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 sm:gap-6">
                        <?php foreach ($related_products as $related): ?>
                            <?php
                            $related_images = explode(',', $related['images'] ?? '');
                            $related_main_index = (int)($related['main_image_index'] ?? 0);
                            $related_main_image = !empty($related_images[$related_main_index]) ? 'uploads/images/' . trim($related_images[$related_main_index]) : (!empty($related_images[0]) ? 'uploads/images/' . trim($related_images[0]) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg==');
                            ?>
                            <div class="bg-white rounded-2xl shadow-md overflow-hidden hover-scale fade-in">
                                <img src="<?php echo htmlspecialchars($related_main_image); ?>" 
                                     alt="<?php echo htmlspecialchars($related['name']); ?>" 
                                     class="w-full h-40 sm:h-48 object-cover"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg=='">
                                <div class="p-4 sm:p-5">
                                    <div class="text-sm text-gray-500 mb-2"><?php echo htmlspecialchars($related['category']); ?></div>
                                    <h3 class="text-base sm:text-lg font-semibold text-gray-800 mb-3 line-clamp-2"><?php echo htmlspecialchars($related['name']); ?></h3>
                                    <div class="text-lg sm:text-xl font-bold text-amber-600 mb-4">$<?php echo number_format($related['price'], 2); ?></div>
                                    <div class="flex space-x-2">
                                        <a href="product-details.php?id=<?php echo $related['id']; ?>" 
                                           class="flex-1 bg-amber-600 text-white py-2 px-3 sm:px-4 rounded-full text-center hover:bg-amber-700 transition-all duration-300 font-medium text-sm sm:text-base">
                                            View Details
                                        </a>
                                        <?php if ($related['stock'] > 0): ?>
                                            <button class="flex-1 bg-white border border-amber-600 text-amber-600 py-2 px-3 sm:px-4 rounded-full hover:bg-amber-50 transition-all duration-300 add-to-cart text-sm sm:text-base" 
                                                    data-product-id="<?php echo $related['id']; ?>">
                                                Add to Cart
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/smooth-loader.js"></script>
</body>
</html>