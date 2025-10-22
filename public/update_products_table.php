<?php
require_once '../app/core/db.php';

try {
    // Add is_featured column if it doesn't exist
    $pdo->exec("ALTER TABLE `products` 
               ADD COLUMN IF NOT EXISTS `is_featured` TINYINT(1) DEFAULT 0,
               ADD COLUMN IF NOT EXISTS `main_image_index` INT DEFAULT 0,
               ADD COLUMN IF NOT EXISTS `images` TEXT DEFAULT NULL");
    
    echo "Database updated successfully. You can now:";
    echo "<br>1. Mark products as featured in the admin panel";
    echo "<br>2. Set main images for products";
    echo "<br>3. View featured products on the homepage";
    
} catch (PDOException $e) {
    die("Error updating database: " . $e->getMessage());
}
