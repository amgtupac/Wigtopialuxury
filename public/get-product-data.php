<?php
/**
 * Get Product Data - AJAX Endpoint for Product Comparison
 */

require_once '../app/core/db.php';

header('Content-Type: application/json');

try {
    $product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($product_id <= 0) {
        throw new Exception('Invalid product ID');
    }
    
    // Get product details
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        throw new Exception('Product not found');
    }
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'product' => $product
    ]);
    
} catch (Exception $e) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
