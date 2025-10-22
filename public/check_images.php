<?php
require_once '../app/core/db.php';

// Check products table for images
try {
    $stmt = $pdo->query('SELECT id, name, images FROM products LIMIT 10');
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h2>Products from Database (First 10):</h2>";
    if (empty($products)) {
        echo "<p>No products found in database. Add products via admin panel.</p>";
    } else {
        foreach ($products as $product) {
            echo "<p><strong>ID:</strong> {$product['id']}, <strong>Name:</strong> {$product['name']}, <strong>Images:</strong> '{$product['images']}'</p>";
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
