<?php
require_once '../app/core/db.php';
require_admin_login();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle search and filter (unchanged)
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? sanitize_input($_GET['category']) : 'all';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter !== 'all') {
    $where_conditions[] = "p.category = ?";
    $params[] = $category_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

try {
    $stmt = $pdo->prepare("
        SELECT p.*,
               c.name as category_name,
               c.icon as category_icon,
               (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) as times_sold,
               (SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.product_id = p.id) as total_quantity_sold
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        $where_clause
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_products,
            COUNT(CASE WHEN stock <= 5 THEN 1 END) as low_stock_products,
            AVG(price) as average_price,
            SUM(stock) as total_stock
        FROM products
    ");
    $product_stats = $stmt->fetch();
    
    // Debug: Check first product's image data
    if (!empty($products)) {
        error_log("First product images: " . ($products[0]['images'] ?? 'NULL'));
        error_log("First product main_image_index: " . ($products[0]['main_image_index'] ?? 'NULL'));
    }
} catch(PDOException $e) {
    die("Error loading products: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Wigtopia Admin - Product Management</title>
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
        .product-card {
            transition: all 0.3s ease;
        }
        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
        }
        @media (max-width: 640px) {
            .product-card {
                transform: none;
            }
            .product-card:hover {
                transform: none;
                box-shadow: none;
            }
            input, button, select, textarea {
                touch-action: manipulation;
            }
            #productModal {
                align-items: flex-end;
            }
            #productModal > div {
                width: 100%;
                max-width: none;
                margin: 0;
                border-radius: 0.75rem 0.75rem 0 0;
                max-height: 80vh;
            }
            .image-preview-container img {
                height: 4rem;
            }
        }
        .overflow-x-auto::-webkit-scrollbar {
            display: none;
        }
        .overflow-x-auto {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .image-upload-container:hover {
            border-color: #f59e0b;
            background-color: #fef3c7;
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
                <a href="products.php" class="flex items-center space-x-3 px-4 py-3 bg-amber-600 text-white rounded-xl">
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
                            <h1 class="text-xl font-bold text-gray-900">Product Management</h1>
                            <p class="text-gray-600 text-sm">Manage your product inventory</p>
                        </div>
                    </div>
                    <div class="flex items-center space-x-3">
                        <button onclick="openAddProductModal()" class="bg-amber-600 text-white px-3 py-1.5 rounded-lg hover:bg-amber-700 transition-colors text-sm">
                            <i class="fas fa-plus mr-1"></i>Add
                        </button>
                        <button onclick="exportProducts()" class="bg-gray-600 text-white px-3 py-1.5 rounded-lg hover:bg-gray-700 transition-colors text-sm">
                            <i class="fas fa-download mr-1"></i>Export
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Product Statistics -->
        <div class="p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Total Products</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($product_stats['total_products'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-box text-3xl text-amber-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Low Stock Items</p>
                            <p class="text-2xl font-bold text-red-600"><?php echo number_format($product_stats['low_stock_products'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-exclamation-triangle text-3xl text-red-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.2s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Average Price</p>
                            <p class="text-2xl font-bold text-green-600">$<?php echo number_format($product_stats['average_price'] ?? 0, 2); ?></p>
                        </div>
                        <i class="fas fa-dollar-sign text-3xl text-green-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.3s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Total Stock</p>
                            <p class="text-2xl font-bold text-blue-600"><?php echo number_format($product_stats['total_stock'] ?? 0); ?></p>
                        </div>
                        <i class="fas fa-cubes text-3xl text-blue-500"></i>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="bg-white rounded-xl shadow-sm border p-4 mb-6">
                <form method="GET" class="flex flex-col sm:flex-row gap-3">
                    <div class="flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Search by name or description..."
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                    </div>
                    <div>
                        <select name="category" class="px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                            <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['name']); ?>"
                                        <?php echo $category_filter === $category['name'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition-colors text-sm">
                        <i class="fas fa-search mr-1"></i>Filter
                    </button>
                </form>
            </div>

            <!-- Products Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-5 gap-3 sm:gap-4">
                <?php if (empty($products)): ?>
                    <div class="col-span-full bg-white rounded-xl shadow-sm border p-6 text-center">
                        <i class="fas fa-box-open text-4xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500 text-sm">No products found matching your criteria.</p>
                        <button onclick="openAddProductModal()" class="mt-3 bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition-colors text-sm">
                            Add Your First Product
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($products as $index => $product): ?>
                        <div class="bg-white rounded-xl shadow-sm border p-4 product-card slide-in-up" style="animation-delay: <?php echo ($index * 0.1) + 0.4; ?>s;">
                            <div class="aspect-w-16 aspect-h-12 mb-3">
                                <?php
                                // Parse images and filter out empty values
                                $images = array_filter(array_map('trim', explode(',', $product['images'] ?? '')), function($img) {
                                    return !empty($img);
                                });
                                $images = array_values($images); // Re-index array
                                
                                // Get main image index, ensuring it's valid
                                $main_index = (int)($product['main_image_index'] ?? 0);
                                
                                // Select the main image, with fallbacks
                                if (!empty($images) && isset($images[$main_index]) && !empty($images[$main_index])) {
                                    $main_image = '../public/uploads/images/' . $images[$main_index];
                                } elseif (!empty($images[0])) {
                                    $main_image = '../public/uploads/images/' . $images[0];
                                } else {
                                    $main_image = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg==';
                                }
                                ?>
                                <img src="<?php echo htmlspecialchars($main_image); ?>"
                                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                                     class="w-full h-32 sm:h-36 object-cover rounded-lg"
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZGRkIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPk5vIEltYWdlPC90ZXh0Pjwvc3ZnPg=='">
                            </div>

                            <div>
                                <div class="mb-2">
                                    <h3 class="text-sm sm:text-base font-semibold text-gray-900 mb-1.5" title="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php echo htmlspecialchars($product['name']); ?>
                                    </h3>
                                    <div class="flex flex-wrap items-center gap-1">
                                        <span class="px-1.5 py-0.5 sm:px-2 sm:py-1 bg-amber-100 text-amber-800 text-[10px] sm:text-xs font-medium rounded-full whitespace-nowrap">
                                            <?php echo htmlspecialchars($product['category_name'] ?? $product['category']); ?>
                                        </span>
                                        <?php if ((bool)($product['featured'] ?? 0)): ?>
                                            <span class="px-1.5 py-0.5 sm:px-2 sm:py-1 bg-green-100 text-green-800 text-[10px] sm:text-xs font-medium rounded-full whitespace-nowrap">Featured</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <p class="text-gray-600 text-xs line-clamp-2 mb-2">
                                    <?php echo htmlspecialchars(substr($product['description'] ?? '', 0, 80) . '...'); ?>
                                </p>

                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xl font-bold text-amber-600">$<?php echo number_format($product['price'], 2); ?></span>
                                    <span class="text-xs <?php echo $product['stock'] <= 5 ? 'text-red-600 font-medium' : 'text-gray-600'; ?>">
                                        Stock: <?php echo number_format($product['stock']); ?>
                                    </span>
                                </div>

                                <?php if ($product['times_sold'] > 0): ?>
                                    <div class="text-xs text-gray-500 mb-2">
                                        <?php echo number_format($product['total_quantity_sold']); ?> sold
                                    </div>
                                <?php endif; ?>

                                <div class="flex space-x-2">
                                    <button onclick="editProduct(<?php echo $product['id']; ?>)"
                                            class="flex-1 bg-amber-600 text-white px-3 py-1.5 rounded-lg hover:bg-amber-700 transition-colors text-xs">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </button>
                                    <button onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')"
                                            class="flex-1 bg-red-600 text-white px-3 py-1.5 rounded-lg hover:bg-red-700 transition-colors text-xs">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Product Modal -->
    <div id="productModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-[60]">
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 max-h-[80vh] overflow-y-auto">
            <div class="p-4 border-b">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900" id="modalTitle">Add New Product</h3>
                    <button onclick="closeProductModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
            </div>
            <form id="productForm" class="p-4">
                <input type="hidden" id="productId" name="product_id">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token'] ?? ''; ?>">
                <input type="hidden" id="mainImageIndex" name="main_image_index" value="0">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Product Name</label>
                        <input type="text" id="productName" name="name" required autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Category</label>
                        <select id="productCategory" name="category" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['name']); ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="productDescription" name="description" rows="3" autocomplete="off"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm"></textarea>
                </div>

                <div class="grid grid-cols-3 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Price</label>
                        <input type="number" id="productPrice" name="price" step="0.01" min="0" required autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Stock</label>
                        <input type="number" id="productStock" name="stock" min="0" required autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Size</label>
                        <input type="text" id="productSize" name="size" autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Color</label>
                        <input type="text" id="productColor" name="color" autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Hair Type</label>
                        <input type="text" id="productHairType" name="hair_type" autocomplete="off"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="flex items-center">
                        <input type="checkbox" id="productFeatured" name="featured" class="h-4 w-4 text-amber-600 focus:ring-amber-500 border-gray-300 rounded">
                        <span class="ml-2 text-sm text-gray-700">Feature this product on homepage</span>
                    </label>
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-2">Product Images</label>

                    <!-- Existing Images Display (for editing) -->
                    <div id="existingImages" class="mb-3 hidden">
                        <p class="text-xs text-gray-600 mb-2">Current Images:</p>
                        <div id="existingImagesContainer" class="grid grid-cols-2 sm:grid-cols-3 gap-2"></div>
                    </div>

                    <!-- Image Upload Area -->
                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center image-upload-container">
                        <input type="file" id="productImages" name="images[]" multiple accept="image/*"
                               class="hidden" onchange="previewImages(this)">
                        <label for="productImages" class="cursor-pointer">
                            <i class="fas fa-cloud-upload-alt text-2xl text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-600">Click to upload images or drag and drop</p>
                            <p class="text-xs text-gray-500 mt-1">PNG, JPG up to 5MB each</p>
                        </label>
                    </div>

                    <!-- Image Preview Area -->
                    <div id="imagePreview" class="mt-3 hidden">
                        <p class="text-xs text-gray-600 mb-2">New Images Preview:</p>
                        <div id="imagePreviewContainer" class="grid grid-cols-2 sm:grid-cols-3 gap-2 image-preview-container"></div>
                    </div>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeProductModal()"
                            class="px-3 py-1.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors text-sm">
                        Cancel
                    </button>
                    <button type="submit" class="bg-amber-600 text-white px-3 py-1.5 rounded-lg hover:bg-amber-700 transition-colors text-sm">
                        <span id="submitText">Add Product</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentProductId = null;
        let existingImages = [];

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

        function openAddProductModal() {
            currentProductId = null;
            existingImages = [];
            document.getElementById('modalTitle').textContent = 'Add New Product';
            document.getElementById('submitText').textContent = 'Add Product';
            document.getElementById('productForm').reset();
            document.getElementById('productId').value = '';
            document.getElementById('productFeatured').checked = false;
            document.getElementById('existingImages').classList.add('hidden');
            document.getElementById('imagePreview').classList.add('hidden');
            document.getElementById('existingImagesContainer').innerHTML = '';
            document.getElementById('imagePreviewContainer').innerHTML = '';
            document.getElementById('productImages').value = '';
            document.getElementById('mainImageIndex').value = '0';
            setMainImageRadio();
            document.getElementById('productModal').classList.remove('hidden');
        }

        function editProduct(productId) {
            currentProductId = productId;
            document.getElementById('modalTitle').textContent = 'Edit Product';
            document.getElementById('submitText').textContent = 'Update Product';

            const product = <?php echo json_encode($products); ?>.find(p => p.id == productId);

            if (product) {
                console.log('Product data:', product);
                console.log('Product images field:', product.images);
                console.log('Product main_image_index:', product.main_image_index);
                
                document.getElementById('productId').value = product.id || '';
                document.getElementById('productName').value = product.name || '';
                document.getElementById('productDescription').value = product.description || '';
                document.getElementById('productPrice').value = product.price || '';
                document.getElementById('productStock').value = product.stock || '';
                document.getElementById('productSize').value = product.size || '';
                document.getElementById('productColor').value = product.color || '';
                document.getElementById('productHairType').value = product.hair_type || '';
                document.getElementById('productFeatured').checked = !!product.featured;
                document.getElementById('mainImageIndex').value = product.main_image_index || 0;

                // Auto-select category in dropdown
                const categorySelect = document.getElementById('productCategory');
                const categoryValue = product.category_name || product.category || '';
                for (let option of categorySelect.options) {
                    if (option.value === categoryValue) {
                        option.selected = true;
                        break;
                    }
                }

                existingImages = product.images ? product.images.split(',').filter(img => img && img.trim() !== '') : [];
                console.log('Existing images array:', existingImages);
                
                const existingSection = document.getElementById('existingImages');
                if (existingImages.length > 0) {
                    existingSection.classList.remove('hidden');
                } else {
                    existingSection.classList.add('hidden');
                }
                showExistingImages(existingImages);
                setMainImageRadio();
            } else {
                alert('Product not found or data incomplete. Please refresh and try again.');
                return;
            }

            document.getElementById('productModal').classList.remove('hidden');
        }

        function deleteProduct(productId, productName) {
            if (confirm(`Are you sure you want to delete "${productName}"? This action cannot be undone.`)) {
                fetch('product_handler.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_product&product_id=${productId}&csrf_token=<?php echo $_SESSION['csrf_token']; ?>`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        location.reload(); // Refresh to show updated product list
                    } else {
                        alert('Error: ' + data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting the product.');
                });
            }
        }

        function closeProductModal() {
            document.getElementById('productModal').classList.add('hidden');
            document.getElementById('imagePreview').classList.add('hidden');
            document.getElementById('existingImages').classList.add('hidden');
            document.getElementById('existingImagesContainer').innerHTML = '';
            document.getElementById('imagePreviewContainer').innerHTML = '';
            document.getElementById('productImages').value = '';
            document.getElementById('productForm').reset();
            document.getElementById('productFeatured').checked = false;
        }

        function exportProducts() {
            alert('Product export feature would be implemented here');
        }

        function previewImages(input) {
            const previewContainer = document.getElementById('imagePreviewContainer');
            const previewSection = document.getElementById('imagePreview');
            previewContainer.innerHTML = '';

            if (input.files && input.files.length > 0) {
                let files = Array.from(input.files);
                const validFiles = files.filter(file => {
                    if (!file.type.startsWith('image/')) {
                        alert(`File "${file.name}" is not an image.`);
                        return false;
                    }
                    if (file.size > 5 * 1024 * 1024) {
                        alert(`File "${file.name}" exceeds 5MB limit.`);
                        return false;
                    }
                    return true;
                });

                if (validFiles.length < files.length) {
                    const dt = new DataTransfer();
                    validFiles.forEach(f => dt.items.add(f));
                    input.files = dt.files;
                    files = validFiles;
                }

                if (files.length > 0) {
                    previewSection.classList.remove('hidden');
                    const loadPromises = [];
                    files.forEach((file, index) => {
                        const reader = new FileReader();
                        const promise = new Promise((resolve) => {
                            reader.onload = function(e) {
                                const previewDiv = document.createElement('div');
                                previewDiv.className = 'flex flex-col items-center space-y-1 border border-gray-200 rounded p-1';

                                const radio = document.createElement('input');
                                radio.type = 'radio';
                                radio.name = 'main_image';
                                radio.value = existingImages.length + index;
                                radio.className = 'main-image-radio';
                                radio.onchange = () => setMainImageIndex(existingImages.length + index);

                                const imgDiv = document.createElement('div');
                                imgDiv.className = 'relative w-full max-w-[80px]';

                                const img = document.createElement('img');
                                img.src = e.target.result;
                                img.className = 'w-full h-16 object-cover rounded-lg border';
                                img.alt = `Preview image ${index + 1}`;

                                const removeBtn = document.createElement('button');
                                removeBtn.type = 'button';
                                removeBtn.onclick = () => removePreviewImage(index);
                                removeBtn.className = 'absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 text-xs opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center';
                                removeBtn.innerHTML = '×';

                                imgDiv.appendChild(img);
                                imgDiv.appendChild(removeBtn);

                                previewDiv.appendChild(radio);
                                previewDiv.appendChild(imgDiv);
                                previewContainer.appendChild(previewDiv);
                                resolve();
                            };
                        });
                        loadPromises.push(promise);
                        reader.readAsDataURL(file);
                    });
                    Promise.all(loadPromises).then(() => {
                        setMainImageRadio();
                    });
                } else {
                    previewSection.classList.add('hidden');
                    setMainImageRadio();
                }
            } else {
                previewSection.classList.add('hidden');
                setMainImageRadio();
            }
        }

        function removePreviewImage(index) {
            const input = document.getElementById('productImages');
            const removedIndex = existingImages.length + index;
            const oldFiles = Array.from(input.files);
            const dt = new DataTransfer();
            oldFiles.forEach((file, i) => {
                if (i !== index) dt.items.add(file);
            });
            input.files = dt.files;

            const mainInput = document.getElementById('mainImageIndex');
            let currentMain = parseInt(mainInput.value) || 0;
            if (currentMain === removedIndex) {
                const prevIndex = removedIndex - 1;
                mainInput.value = Math.max(0, prevIndex);
            } else if (currentMain > removedIndex) {
                mainInput.value = currentMain - 1;
            }

            previewImages(input);
        }

        function showExistingImages(images) {
            const existingContainer = document.getElementById('existingImagesContainer');
            const existingSection = document.getElementById('existingImages');
            existingContainer.innerHTML = '';

            if (images && images.length > 0) {
                existingSection.classList.remove('hidden');
                images.forEach((imageName, index) => {
                    const cleanImageName = imageName.trim();
                    if (cleanImageName) {
                        const previewDiv = document.createElement('div');
                        previewDiv.className = 'flex flex-col items-center space-y-1 border border-gray-200 rounded p-1 group';

                        const radio = document.createElement('input');
                        radio.type = 'radio';
                        radio.name = 'main_image';
                        radio.value = index;
                        radio.className = 'main-image-radio';
                        radio.onchange = () => setMainImageIndex(index);

                        const imgDiv = document.createElement('div');
                        imgDiv.className = 'relative w-full max-w-[80px]';

                        const img = document.createElement('img');
                        img.src = `../public/uploads/images/${cleanImageName}`;
                        img.className = 'w-full h-16 object-cover rounded-lg border';
                        img.alt = `Current image ${index + 1}`;
                        img.onerror = () => {
                            img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iNjQiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PHJlY3Qgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIgZmlsbD0iI2RkZCIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwsIHNhbnMtc2VyaWYiIGZvbnQtc2l6ZT0iMTAiIGZpbGw9IiM5OTkiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5ObyBJbWFnZTwvdGV4dD48L3N2Zz4=';
                        };

                        const removeBtn = document.createElement('button');
                        removeBtn.type = 'button';
                        removeBtn.onclick = () => removeExistingImage(index);
                        removeBtn.className = 'absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-5 h-5 text-xs opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center';
                        removeBtn.innerHTML = '×';

                        imgDiv.appendChild(img);
                        imgDiv.appendChild(removeBtn);

                        previewDiv.appendChild(radio);
                        previewDiv.appendChild(imgDiv);
                        existingContainer.appendChild(previewDiv);
                    }
                });
            } else {
                existingSection.classList.add('hidden');
            }
            setMainImageRadio();
        }

        function removeExistingImage(index) {
            const removedIndex = index;
            existingImages.splice(index, 1);
            const mainInput = document.getElementById('mainImageIndex');
            let currentMain = parseInt(mainInput.value) || 0;
            if (currentMain === removedIndex) {
                const prevIndex = removedIndex - 1;
                mainInput.value = Math.max(0, prevIndex);
            } else if (currentMain > removedIndex) {
                mainInput.value = currentMain - 1;
            }

            const existingSection = document.getElementById('existingImages');
            if (existingImages.length > 0) {
                existingSection.classList.remove('hidden');
            } else {
                existingSection.classList.add('hidden');
            }
            showExistingImages(existingImages);

            const input = document.getElementById('productImages');
            if (input.files && input.files.length > 0) {
                previewImages(input);
            } else {
                setMainImageRadio();
            }
        }

        function setMainImageIndex(index) {
            document.getElementById('mainImageIndex').value = index;
            setMainImageRadio();
        }

        function setMainImageRadio() {
            document.querySelectorAll('.main-image-radio').forEach(r => r.checked = false);
            const mainIdx = parseInt(document.getElementById('mainImageIndex').value) || 0;
            const targetRadio = document.querySelector(`.main-image-radio[value="${mainIdx}"]`);
            if (targetRadio) {
                targetRadio.checked = true;
                // Highlight the selected image container
                targetRadio.closest('.border').classList.add('border-amber-400', 'bg-amber-50');
            }
            // Remove highlight from others
            document.querySelectorAll('.border.border-gray-200').forEach(div => {
                if (!div.querySelector('.main-image-radio:checked')) {
                    div.classList.remove('border-amber-400', 'bg-amber-50');
                }
            });
        }

        document.getElementById('productModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProductModal();
            }
        });

        document.getElementById('productForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Basic form validation
            const name = document.getElementById('productName').value.trim();
            const category = document.getElementById('productCategory').value.trim();
            const price = document.getElementById('productPrice').value.trim();
            const stock = document.getElementById('productStock').value.trim();

            if (!name) {
                alert('Product name is required.');
                document.getElementById('productName').focus();
                return;
            }
            if (!category) {
                alert('Product category is required.');
                document.getElementById('productCategory').focus();
                return;
            }
            if (!price || isNaN(price) || parseFloat(price) < 0) {
                alert('Valid product price is required.');
                document.getElementById('productPrice').focus();
                return;
            }
            if (!stock || isNaN(stock) || parseInt(stock) < 0) {
                alert('Valid product stock is required.');
                document.getElementById('productStock').focus();
                return;
            }

            const formData = new FormData(this);
            const action = currentProductId ? 'update_product' : 'add_product';
            formData.append('action', action);
            formData.append('existing_images', JSON.stringify(existingImages));

            // Show loading state
            const submitButton = document.querySelector('#productForm button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>Saving...';
            submitButton.disabled = true;

            fetch('product_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeProductModal();
                    location.reload(); // Refresh to show new/updated product
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while saving the product.');
            })
            .finally(() => {
                // Restore button state
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            });
        });

        // Drag and drop functionality
        document.addEventListener('DOMContentLoaded', function() {
            const uploadArea = document.querySelector('.image-upload-container');
            const fileInput = document.getElementById('productImages');

            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                uploadArea.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, unhighlight, false);
            });

            function highlight(e) {
                uploadArea.classList.add('border-amber-400', 'bg-amber-50');
            }

            function unhighlight(e) {
                uploadArea.classList.remove('border-amber-400', 'bg-amber-50');
            }

            uploadArea.addEventListener('drop', handleDrop, false);

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                fileInput.files = files;
                previewImages(fileInput);
            }

            uploadArea.addEventListener('click', function() {
                fileInput.click();
            });
        });
    </script>
    <script src="../public/assets/js/smooth-loader.js"></script>
</body>
</html>