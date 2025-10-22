<?php
require_once '../app/core/db.php';

// Handle search functionality
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? sanitize_input($_GET['category']) : 'all';
$sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'created_at DESC';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Build query with search and category
$where_conditions = [];
$params = [];

if ($category_filter !== 'all') {
    $where_conditions[] = "category = ?";
    $params[] = $category_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR description LIKE ? OR category LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count for pagination
try {
    $count_sql = "SELECT COUNT(*) FROM products $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetchColumn();
} catch (PDOException $e) {
    $total_products = 0;
}

$total_pages = ceil($total_products / $per_page);

// Get products
try {
    $sql = "SELECT * FROM products $where_clause ORDER BY $sort LIMIT $per_page OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (PDOException $e) {
    $products = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo !empty($search) ? "Search: $search - " : ''; ?>Products - Wigtopia</title>
    <meta name="description" content="<?php echo !empty($search) ? "Search results for '$search' - " : ''; ?>Browse our collection of premium wigs, hair extensions, and accessories.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="assets/css/smooth-animations.css" rel="stylesheet">
    <link href="assets/css/dark-mode.css" rel="stylesheet">
    <link href="assets/css/loading-skeleton.css" rel="stylesheet">
    <style>
        .hover-scale { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .hover-scale:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
        .fade-in { animation: fadeIn 0.6s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'header.php'; ?>
    
    <main class="py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto bg-gradient-to-r from-amber-50/50 to-amber-100/50 rounded-3xl p-6 sm:p-8 shadow-lg fade-in">
            <!-- Page Header -->
            <div class="text-center mb-8">
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-gray-800 mb-3">
                    <?php if ($category_filter !== 'all'): ?>
                        <?php echo htmlspecialchars($category_filter); ?> Products
                    <?php elseif (!empty($search)): ?>
                        Search Results for "<?php echo htmlspecialchars($search); ?>"
                    <?php else: ?>
                        All Products
                    <?php endif; ?>
                </h1>
                <p class="text-gray-600 text-lg">
                    <?php if ($category_filter !== 'all'): ?>
                        Browse our <?php echo htmlspecialchars($category_filter); ?> collection
                    <?php elseif (!empty($search)): ?>
                        Found <?php echo $total_products; ?> product<?php echo $total_products !== 1 ? 's' : ''; ?> matching "<?php echo htmlspecialchars($search); ?>"
                    <?php else: ?>
                        Showing <?php echo count($products); ?> of <?php echo $total_products; ?> products
                    <?php endif; ?>
                    <?php if ($total_pages > 1): ?>
                        <span class="text-amber-600">(Page <?php echo $page; ?> of <?php echo $total_pages; ?>)</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <!-- Search Bar -->
            <div class="mb-8 max-w-2xl mx-auto">
                <form method="GET" class="flex bg-white rounded-full shadow-lg overflow-hidden">
                    <input type="text" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" 
                           placeholder="Search products..." 
                           class="flex-1 px-6 py-4 text-gray-700 outline-none focus:ring-2 focus:ring-amber-400">
                    <button type="submit" class="bg-gradient-to-r from-amber-600 to-amber-700 text-white px-6 py-4 hover:from-amber-700 hover:to-amber-800 transition-all duration-300 flex items-center">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" class="mr-2">
                            <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                        </svg>
                        Search
                    </button>
                </form>
                <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                    <div class="mt-4 text-center">
                        <a href="products.php" class="inline-flex items-center bg-white text-amber-600 px-6 py-2 rounded-full border border-amber-600 hover:bg-amber-50 transition-all duration-300">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" class="mr-2">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                            Clear Search
                        </a>
                    </div>
                <?php endif; ?>
                <?php if ($category_filter !== 'all'): ?>
                    <div class="mt-4 text-center">
                        <a href="products.php" class="inline-flex items-center bg-white text-amber-600 px-6 py-2 rounded-full border border-amber-600 hover:bg-amber-50 transition-all duration-300">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" class="mr-2">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                            Clear Category Filter
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Products Grid -->
            <?php if (empty($products)): ?>
                <div class="text-center py-16">
                    <div class="text-6xl mb-4">🔍</div>
                    <h3 class="text-2xl font-semibold text-gray-600 mb-2">No products found</h3>
                    <p class="text-gray-500 mb-6">Try adjusting your search terms or filters.</p>
                    <a href="products.php" class="bg-amber-600 text-white py-3 px-8 rounded-full hover:bg-amber-700 transition-all duration-300">View All Products</a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 sm:gap-6 mb-12">
                    <?php foreach ($products as $product): ?>
                        <?php
                        $images = explode(',', $product['images'] ?? '');
                        $main_index = (int)($product['main_image_index'] ?? 0);
                        $main_image = !empty($images[$main_index]) ? 'uploads/images/' . trim($images[$main_index]) : (!empty($images[0]) ? 'uploads/images/' . trim($images[0]) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg==');
                        ?>
                        <div class="bg-white rounded-2xl shadow-md overflow-hidden hover-scale fade-in">
                            <!-- Badges -->
                            <div class="relative">
                                <div class="absolute top-3 left-3 space-y-1">
                                    <?php if (isset($product['is_featured']) && $product['is_featured']): ?>
                                        <span class="bg-amber-500 text-white text-xs font-bold px-2 py-1 rounded-full">Featured</span>
                                    <?php endif; ?>
                                    <?php if (isset($product['is_new']) && $product['is_new']): ?>
                                        <span class="bg-green-500 text-white text-xs font-bold px-2 py-1 rounded-full">New</span>
                                    <?php endif; ?>
                                    <?php if (isset($product['is_sale']) && $product['is_sale']): ?>
                                        <span class="bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full">Sale</span>
                                    <?php endif; ?>
                                </div>
                                
                                <img src="<?php echo htmlspecialchars($main_image); ?>" 
                                     alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                     class="w-full h-40 sm:h-48 object-cover"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg=='">
                            </div>
                            
                            <div class="p-4 sm:p-5">
                                <div class="text-sm text-gray-500 mb-2"><?php echo htmlspecialchars($product['category']); ?></div>
                                <h3 class="text-base sm:text-lg font-semibold text-gray-800 mb-3 line-clamp-2"><?php echo htmlspecialchars($product['name']); ?></h3>
                                
                                <!-- Product Details -->
                                <div class="space-y-1 text-xs sm:text-sm text-gray-600 mb-3">
                                    <?php if (!empty($product['size'])): ?>
                                        <p><span class="font-medium">Size:</span> <?php echo htmlspecialchars($product['size']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($product['color'])): ?>
                                        <p><span class="font-medium">Color:</span> <?php echo htmlspecialchars($product['color']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($product['hair_type'])): ?>
                                        <p><span class="font-medium">Type:</span> <?php echo htmlspecialchars($product['hair_type']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Price and Stock -->
                                <div class="flex items-center justify-between mb-4">
                                    <div class="text-lg sm:text-2xl font-bold text-amber-600">$<?php echo number_format($product['price'], 2); ?></div>
                                    <div class="text-xs sm:text-sm <?php echo $product['stock'] <= 0 ? 'text-red-500' : ($product['stock'] <= 5 ? 'text-yellow-600' : 'text-green-600'); ?>">
                                        <?php if ($product['stock'] <= 0): ?>
                                            Out of Stock
                                        <?php elseif ($product['stock'] <= 5): ?>
                                            Only <?php echo $product['stock']; ?> left!
                                        <?php else: ?>
                                            In Stock
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="flex space-x-2">
                                    <a href="product-details.php?id=<?php echo $product['id']; ?>" 
                                       class="flex-1 bg-amber-600 text-white py-2 px-3 sm:px-4 rounded-full text-center hover:bg-amber-700 transition-all duration-300 font-medium text-sm sm:text-base">
                                        View Details
                                    </a>
                                    <?php if ($product['stock'] > 0): ?>
                                        <button class="flex-1 bg-white border border-amber-600 text-amber-600 py-2 px-3 sm:px-4 rounded-full hover:bg-amber-50 transition-all duration-300 add-to-cart text-sm sm:text-base" 
                                                data-product-id="<?php echo $product['id']; ?>">
                                            Add to Cart
                                        </button>
                                    <?php else: ?>
                                        <button class="flex-1 bg-gray-200 text-gray-500 py-2 px-3 sm:px-4 rounded-full cursor-not-allowed text-sm sm:text-base" disabled>
                                            Sold Out
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="flex flex-wrap justify-center items-center gap-2 mt-12">
                        <?php
                        // Build query string for pagination
                        $query_params = $_GET;
                        unset($query_params['page']);
                        $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $query_string; ?>" 
                               class="px-4 py-2 bg-amber-600 text-white rounded-full hover:bg-amber-700 transition-all duration-300">
                                ← Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="px-4 py-2 bg-amber-800 text-white rounded-full font-semibold"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $query_string; ?>" 
                                   class="px-4 py-2 bg-white text-amber-600 rounded-full border border-amber-600 hover:bg-amber-50 transition-all duration-300"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $query_string; ?>" 
                               class="px-4 py-2 bg-amber-600 text-white rounded-full hover:bg-amber-700 transition-all duration-300">
                                Next →
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/smooth-loader.js"></script>
    <script src="assets/js/loading-skeleton.js"></script>
    <script src="assets/js/dark-mode.js"></script>
    <script src="assets/js/advanced-search.js"></script>
    <script src="assets/js/infinite-scroll.js"></script>
</body>
</html>