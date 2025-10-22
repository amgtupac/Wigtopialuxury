<?php
require_once '../app/core/db.php';
require_admin_login();

// CSRF token validation
if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Handle AJAX requests for product management
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'add_product':
            handleAddProduct();
            break;
        case 'update_product':
            handleUpdateProduct();
            break;
        case 'delete_product':
            handleDeleteProduct();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

// Regenerate CSRF token after successful request
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

function handleAddProduct() {
    // Validate required fields
    $required_fields = ['name', 'category', 'price', 'stock'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception("Field '$field' is required");
        }
    }

    $name = sanitize_input($_POST['name']);
    $category = sanitize_input($_POST['category']);
    $description = sanitize_input($_POST['description'] ?? '');
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $size = sanitize_input($_POST['size'] ?? '');
    $color = sanitize_input($_POST['color'] ?? '');
    $hair_type = sanitize_input($_POST['hair_type'] ?? '');
    $featured = isset($_POST['featured']) && $_POST['featured'] === 'on' ? 1 : 0;

    // Get category_id
    $category_stmt = $GLOBALS['pdo']->prepare("SELECT id FROM categories WHERE name = ?");
    $category_stmt->execute([$category]);
    $category_result = $category_stmt->fetch();

    if (!$category_result) {
        throw new Exception("Invalid category: $category");
    }
    $category_id = $category_result['id'];

    // Handle image uploads
    $uploaded_images = [];
    if (!empty($_FILES['images']['name'][0])) {
        $uploaded_images = handleImageUploads();
    }

    // If no images uploaded, use a default placeholder
    if (empty($uploaded_images)) {
        $uploaded_images = ['placeholder.jpg'];
    }

    $images_string = implode(',', $uploaded_images);

    $main_image_index = (int)($_POST['main_image_index'] ?? 0);
    if ($main_image_index >= count($uploaded_images)) {
        $main_image_index = 0;
    }

    // Insert product
    $stmt = $GLOBALS['pdo']->prepare("
        INSERT INTO products (name, description, category, category_id, size, color, hair_type, price, stock, images, main_image_index, featured, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([$name, $description, $category, $category_id, $size, $color, $hair_type, $price, $stock, $images_string, $main_image_index, $featured]);

    echo json_encode([
        'success' => true,
        'message' => 'Product added successfully',
        'product_id' => $GLOBALS['pdo']->lastInsertId()
    ]);
}

function handleUpdateProduct() {
    $product_id = (int)$_POST['product_id'];
    if (!$product_id) {
        throw new Exception('Product ID is required');
    }

    // Check if product exists
    $stmt = $GLOBALS['pdo']->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $existing_product = $stmt->fetch();

    if (!$existing_product) {
        throw new Exception('Product not found');
    }

    // Validate required fields
    $required_fields = ['name', 'category', 'price', 'stock'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
            throw new Exception("Field '$field' is required");
        }
    }

    $name = sanitize_input($_POST['name']);
    $category = sanitize_input($_POST['category']);
    $description = sanitize_input($_POST['description'] ?? '');
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $size = sanitize_input($_POST['size'] ?? '');
    $color = sanitize_input($_POST['color'] ?? '');
    $hair_type = sanitize_input($_POST['hair_type'] ?? '');
    $featured = isset($_POST['featured']) && $_POST['featured'] === 'on' ? 1 : 0;

    // Get category_id
    $category_stmt = $GLOBALS['pdo']->prepare("SELECT id FROM categories WHERE name = ?");
    $category_stmt->execute([$category]);
    $category_result = $category_stmt->fetch();

    if (!$category_result) {
        throw new Exception("Invalid category: $category");
    }
    $category_id = $category_result['id'];

    // Handle existing images
    $existing_images = [];
    if (isset($_POST['existing_images']) && !empty($_POST['existing_images'])) {
        $existing_images = json_decode($_POST['existing_images'], true);
        if (!is_array($existing_images)) {
            $existing_images = [];
        }
    }

    // Handle new image uploads
    $new_images = [];
    if (!empty($_FILES['images']['name'][0])) {
        $new_images = handleImageUploads();
    }

    // Combine existing and new images
    $all_images = array_merge($existing_images, $new_images);

    // If no images at all, use a default placeholder
    if (empty($all_images)) {
        $all_images = ['placeholder.jpg'];
    }

    $images_string = implode(',', $all_images);

    $main_image_index = (int)($_POST['main_image_index'] ?? 0);
    if ($main_image_index >= count($all_images)) {
        $main_image_index = 0;
    }

    // Update product
    $stmt = $GLOBALS['pdo']->prepare("
        UPDATE products
        SET name = ?, description = ?, category = ?, category_id = ?, size = ?, color = ?, hair_type = ?, price = ?, stock = ?, images = ?, main_image_index = ?, featured = ?
        WHERE id = ?
    ");

    $stmt->execute([$name, $description, $category, $category_id, $size, $color, $hair_type, $price, $stock, $images_string, $main_image_index, $featured, $product_id]);

    echo json_encode([
        'success' => true,
        'message' => 'Product updated successfully'
    ]);
}

function handleDeleteProduct() {
    $product_id = (int)$_POST['product_id'];
    if (!$product_id) {
        throw new Exception('Product ID is required');
    }

    // Check if product exists
    $stmt = $GLOBALS['pdo']->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();

    if (!$product) {
        throw new Exception('Product not found');
    }

    // Get image filenames to delete
    $images = explode(',', $product['images'] ?? '');
    $images_to_delete = array_filter($images);

    // Delete product
    $stmt = $GLOBALS['pdo']->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$product_id]);

    // Delete image files (skip placeholder)
    foreach ($images_to_delete as $image) {
        $trimmed_image = trim($image);
        if ($trimmed_image !== 'placeholder.jpg') {
            $image_path = '../public/uploads/images/' . $trimmed_image;
            if (file_exists($image_path)) {
                unlink($image_path);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Product deleted successfully'
    ]);
}

function handleImageUploads() {
    $uploaded_images = [];
    $upload_dir = '../public/uploads/images/';

    // Ensure upload directory exists
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Process each uploaded file
    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
            $file_name = $_FILES['images']['name'][$key];
            $file_size = $_FILES['images']['size'][$key];
            $file_tmp = $_FILES['images']['tmp_name'][$key];

            // Additional security: Check for null bytes or path traversal
            if (strpos($file_name, "\0") !== false || preg_match('/[\.]{2}[\/\\\\]/', $file_name)) {
                throw new Exception("Invalid filename: $file_name");
            }

            // Validate file type using MIME and magic bytes
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            $file_info = finfo_open(FILEINFO_MIME_TYPE);
            $file_type = finfo_file($file_info, $file_tmp);
            finfo_close($file_info);

            if (!in_array($file_type, $allowed_types)) {
                throw new Exception("Invalid file type: $file_name. Only JPG, PNG, and WebP images are allowed.");
            }

            // Additional check: Verify it's a valid image by attempting to load it
            $image_check = null;
            switch ($file_type) {
                case 'image/jpeg':
                case 'image/jpg':
                    $image_check = @imagecreatefromjpeg($file_tmp);
                    break;
                case 'image/png':
                    $image_check = @imagecreatefrompng($file_tmp);
                    break;
                case 'image/webp':
                    $image_check = @imagecreatefromwebp($file_tmp);
                    break;
            }
            if (!$image_check) {
                throw new Exception("Invalid image file: $file_name");
            }
            imagedestroy($image_check);

            // Validate file size (5MB max)
            if ($file_size > 5 * 1024 * 1024) {
                throw new Exception("File too large: $file_name. Maximum size is 5MB.");
            }

            // Generate unique filename
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $unique_name = uniqid('', true) . '.' . strtolower($file_extension);
            $target_path = $upload_dir . $unique_name;

            // Move uploaded file
            if (!move_uploaded_file($file_tmp, $target_path)) {
                throw new Exception("Failed to upload file: $file_name");
            }

            // Optional: Resize or re-encode for added security (e.g., strip metadata)
            // For now, skip to keep simple; can add GD/ImageMagick processing here

            $uploaded_images[] = $unique_name;
        }
    }

    return $uploaded_images;
}
?>