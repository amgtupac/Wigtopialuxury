<?php
require_once '../app/core/db.php';
require_admin_login();

$message = '';

// Handle form submissions for adding/editing categories (unchanged)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = isset($_POST['action']) ? sanitize_input($_POST['action']) : '';

    if ($action === 'add_category') {
        $name = sanitize_input($_POST['name']);
        $icon = sanitize_input($_POST['icon']);
        $description = sanitize_input($_POST['description']);

        if (empty($name) || empty($icon)) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Name and icon are required.</div>';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, icon, description) VALUES (?, ?, ?)");
                $stmt->execute([$name, $icon, $description]);
                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">Category added successfully!</div>';
            } catch(PDOException $e) {
                if ($e->getCode() == 23000) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Category name already exists.</div>';
                } else {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Error adding category: ' . $e->getMessage() . '</div>';
                }
            }
        }
    } elseif ($action === 'edit_category') {
        $id = (int)$_POST['category_id'];
        $name = sanitize_input($_POST['name']);
        $icon = sanitize_input($_POST['icon']);
        $description = sanitize_input($_POST['description']);

        if (empty($name) || empty($icon)) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Name and icon are required.</div>';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, icon = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $icon, $description, $id]);
                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">Category updated successfully!</div>';
            } catch(PDOException $e) {
                if ($e->getCode() == 23000) {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Category name already exists.</div>';
                } else {
                    $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Error updating category: ' . $e->getMessage() . '</div>';
                }
            }
        }
    } elseif ($action === 'delete_category') {
        $id = (int)$_POST['category_id'];

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $stmt->execute([$id]);
            $product_count = $stmt->fetchColumn();

            if ($product_count > 0) {
                $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Cannot delete category with existing products. Move or delete products first.</div>';
            } else {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $message = '<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">Category deleted successfully!</div>';
            }
        } catch(PDOException $e) {
            $message = '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">Error deleting category: ' . $e->getMessage() . '</div>';
        }
    }
}

// Get all categories with product counts (unchanged)
try {
    $stmt = $pdo->query("
        SELECT c.*,
               (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
        FROM categories c
        ORDER BY c.name
    ");
    $categories = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_categories,
            SUM(product_count) as total_products
        FROM (
            SELECT (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
            FROM categories c
        ) category_products
    ");
    $category_stats = $stmt->fetch();
} catch(PDOException $e) {
    die("Error loading categories: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Wigtopia Admin - Category Management</title>
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
        .category-card {
            transition: all 0.3s ease;
            touch-action: manipulation;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        /* Modal adjustments for mobile */
        @media (max-width: 640px) {
            #categoryModal {
                align-items: flex-end;
            }
            #categoryModal > div {
                width: 100%;
                max-width: none;
                margin: 0;
                border-radius: 0.75rem 0.75rem 0 0;
            }
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
                <a href="products.php" class="flex items-center space-x-3 px-4 py-3 text-amber-200 hover:bg-amber-700 hover:text-white rounded-xl transition-all duration-300">
                    <i class="fas fa-box"></i>
                    <span>Products</span>
                </a>
                <a href="categories.php" class="flex items-center space-x-3 px-4 py-3 bg-amber-600 text-white rounded-xl">
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
                            <h1 class="text-xl font-bold text-gray-900">Category Management</h1>
                            <p class="text-gray-600 text-sm">Organize your product/shades categories</p>
                        </div>
                    </div>
                    <div>
                        <button onclick="openAddCategoryModal()" class="bg-amber-600 text-white px-3 py-1.5 rounded-lg hover:bg-amber-700 transition-colors text-sm">
                            <i class="fas fa-plus mr-1"></i>Add
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="px-4 py-4">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Category Statistics -->
        <div class="p-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Total Categories</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($category_stats['total_categories']); ?></p>
                        </div>
                        <i class="fas fa-tags text-3xl text-amber-500"></i>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border p-4 slide-in-up" style="animation-delay: 0.1s;">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-600 text-xs">Total Products</p>
                            <p class="text-2xl font-bold text-gray-900"><?php echo number_format($category_stats['total_products']); ?></p>
                        </div>
                        <i class="fas fa-box text-3xl text-blue-500"></i>
                    </div>
                </div>
            </div>

            <!-- Categories Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php if (empty($categories)): ?>
                    <div class="col-span-full bg-white rounded-xl shadow-sm border p-6 text-center">
                        <i class="fas fa-tags text-5xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500 text-base">No categories found.</p>
                        <button onclick="openAddCategoryModal()" class="mt-3 bg-amber-600 text-white px-4 py-1.5 rounded-lg hover:bg-amber-700 transition-colors text-sm">
                            Create Your First Category
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($categories as $index => $category): ?>
                        <div class="bg-white rounded-xl shadow-sm border p-4 category-card slide-in-up" style="animation-delay: <?php echo ($index * 0.1) + 0.2; ?>s;">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-2">
                                    <div class="w-10 h-10 bg-amber-100 rounded-full flex items-center justify-center">
                                        <span class="text-xl"><?php echo htmlspecialchars($category['icon']); ?></span>
                                    </div>
                                    <div>
                                        <h3 class="text-base font-semibold text-gray-900">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </h3>
                                        <p class="text-xs text-gray-500">
                                            <?php echo number_format($category['product_count']); ?> products
                                        </p>
                                    </div>
                                </div>
                                <div class="flex space-x-1">
                                    <button onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', '<?php echo htmlspecialchars($category['icon']); ?>', '<?php echo htmlspecialchars($category['description']); ?>')"
                                            class="text-amber-600 hover:text-amber-800 p-1.5">
                                        <i class="fas fa-edit text-sm"></i>
                                    </button>
                                    <button onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')"
                                            class="text-red-600 hover:text-red-800 p-1.5">
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                </div>
                            </div>

                            <?php if ($category['description']): ?>
                                <p class="text-gray-600 text-xs mb-3 line-clamp-2">
                                    <?php echo htmlspecialchars($category['description']); ?>
                                </p>
                            <?php endif; ?>

                            <div class="flex items-center justify-between text-xs">
                                <span class="text-gray-500">
                                    Created: <?php echo date('M j, Y', strtotime($category['created_at'])); ?>
                                </span>
                                <a href="products.php?category=<?php echo urlencode($category['name']); ?>"
                                   class="text-amber-600 hover:text-amber-800 font-medium">
                                    View Products →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Category Modal -->
    <div id="categoryModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center z-[60]">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full mx-4">
            <div class="p-4 border-b">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-semibold text-gray-900" id="categoryModalTitle">Add New Category</h3>
                    <button onclick="closeCategoryModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fas fa-times text-sm"></i>
                    </button>
                </div>
            </div>
            <form id="categoryForm" method="POST" class="p-4">
                <input type="hidden" name="action" value="">
                <input type="hidden" id="categoryId" name="category_id" value="">

                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Category Name</label>
                    <input type="text" id="categoryName" name="name" required
                           class="w-full px-3 py-1.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm">
                </div>

                <div class="mb-3">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Icon (Emoji)</label>
                    <input type="text" id="categoryIcon" name="icon" required maxlength="2"
                           class="w-full px-3 py-1.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm"
                           placeholder="e.g., 💇‍♀️">
                </div>

                <div class="mb-4">
                    <label class="block text-xs font-medium text-gray-700 mb-1">Description (Optional)</label>
                    <textarea id="categoryDescription" name="description" rows="3"
                              class="w-full px-3 py-1.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 text-sm"
                              placeholder="Brief description of this category..."></textarea>
                </div>

                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeCategoryModal()"
                            class="px-3 py-1.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors text-sm">
                        Cancel
                    </button>
                    <button type="submit" class="bg-amber-600 text-white px-3 py-1.5 rounded-lg hover:bg-amber-700 transition-colors text-sm">
                        <span id="categorySubmitText">Add Category</span>
                    </button>
                </div>
            </form>
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

        function openAddCategoryModal() {
            document.getElementById('categoryModalTitle').textContent = 'Add New Category';
            document.getElementById('categorySubmitText').textContent = 'Add Category';
            document.getElementById('categoryForm').reset();
            document.getElementById('categoryId').value = '';
            document.getElementById('categoryName').value = '';
            document.getElementById('categoryIcon').value = '';
            document.getElementById('categoryDescription').value = '';
            document.getElementById('categoryForm').querySelector('[name="action"]').value = 'add_category';
            document.getElementById('categoryModal').classList.remove('hidden');
        }

        function editCategory(categoryId, name, icon, description) {
            document.getElementById('categoryModalTitle').textContent = 'Edit Category';
            document.getElementById('categorySubmitText').textContent = 'Update Category';
            document.getElementById('categoryId').value = categoryId;
            document.getElementById('categoryName').value = name;
            document.getElementById('categoryIcon').value = icon;
            document.getElementById('categoryDescription').value = description;
            document.getElementById('categoryForm').querySelector('[name="action"]').value = 'edit_category';
            document.getElementById('categoryModal').classList.remove('hidden');
        }

        function deleteCategory(categoryId, categoryName) {
            if (confirm(`Are you sure you want to delete the category "${categoryName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" value="${categoryId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function closeCategoryModal() {
            document.getElementById('categoryModal').classList.add('hidden');
        }

        document.getElementById('categoryModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeCategoryModal();
            }
        });
    </script>
    <script src="../public/assets/js/smooth-loader.js"></script>
</body>
</html>
