<?php
require_once '../app/core/db.php';

try {
    // Add is_featured column if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'is_featured'");
    $featuredColumnExists = $stmt->rowCount() > 0;

    // Add main_image_index column if it doesn't exist
    $stmt = $pdo->query("SHOW COLUMNS FROM products LIKE 'main_image_index'");
    $imageIndexColumnExists = $stmt->rowCount() > 0;

    $pdo->beginTransaction();

    if (!$featuredColumnExists) {
        $pdo->exec("ALTER TABLE products ADD COLUMN is_featured BOOLEAN DEFAULT 0");
        echo "Added 'is_featured' column to products table.\n";
        
        // Mark some products as featured by default (optional)
        $pdo->exec("UPDATE products SET is_featured = 1 WHERE id IN (1, 4, 7, 10)");
        echo "Marked some products as featured.\n";
    } else {
        echo "'is_featured' column already exists in products table.\n";
    }

    if (!$imageIndexColumnExists) {
        $pdo->exec("ALTER TABLE products ADD COLUMN main_image_index INT DEFAULT 0");
        echo "Added 'main_image_index' column to products table.\n";
    } else {
        echo "'main_image_index' column already exists in products table.\n";
    }
    
    $pdo->commit();
    echo "Setup completed successfully. You can now access the admin panel.\n";
} catch (PDOException $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
