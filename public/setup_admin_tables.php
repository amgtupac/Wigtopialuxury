<?php
require_once '../app/core/db.php';

try {
    // Create admin activity log table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `admin_activity_log` (
          `id` int NOT NULL AUTO_INCREMENT,
          `admin_id` int NOT NULL,
          `action` varchar(100) NOT NULL,
          `details` text,
          `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `admin_id` (`admin_id`),
          KEY `timestamp` (`timestamp`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
    ");

    // Create user activity log table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `user_activity_log` (
          `id` int NOT NULL AUTO_INCREMENT,
          `user_id` int DEFAULT NULL,
          `action` varchar(100) NOT NULL,
          `details` text,
          `ip_address` varchar(45) DEFAULT NULL,
          `user_agent` text,
          `timestamp` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`),
          KEY `timestamp` (`timestamp`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
    ");

    // Add payment proof path column to orders table if not exists
    $stmt = $pdo->query("DESCRIBE orders");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    if (!in_array('payment_proof_path', $columnNames)) {
        $pdo->exec("ALTER TABLE `orders` ADD COLUMN `payment_proof_path` varchar(255) DEFAULT NULL AFTER `payment_proof`;");
    }

    // Add main_image_index column to products table if not exists
    $stmt = $pdo->query("DESCRIBE products");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');
    if (!in_array('main_image_index', $columnNames)) {
        $pdo->exec("ALTER TABLE `products` ADD COLUMN `main_image_index` INT DEFAULT 0;");
    }

    // Add featured column to products table if not exists
    if (!in_array('featured', $columnNames)) {
        $pdo->exec("ALTER TABLE `products` ADD COLUMN `featured` BOOLEAN DEFAULT 0;");
    }

    // Create admin password reset tokens table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `admin_password_resets` (
          `id` int NOT NULL AUTO_INCREMENT,
          `admin_id` int NOT NULL,
          `token` varchar(64) NOT NULL,
          `expiry` datetime NOT NULL,
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `admin_id` (`admin_id`),
          KEY `token` (`token`),
          KEY `expiry` (`expiry`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
    ");

    echo "Admin database tables updated successfully!";

} catch(PDOException $e) {
    echo "Error updating admin tables: " . $e->getMessage();
}
?>
