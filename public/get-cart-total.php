<?php
require_once '../app/core/db.php';

header('Content-Type: application/json');

$subtotal = 0;

if (isset($_SESSION['cart']) && is_array($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    try {
        $product_ids = array_keys($_SESSION['cart']);
        $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
        
        $stmt = $pdo->prepare("SELECT id, price FROM products WHERE id IN ($placeholders)");
        $stmt->execute($product_ids);
        $products = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        foreach ($_SESSION['cart'] as $product_id => $quantity) {
            if (isset($products[$product_id])) {
                $subtotal += $products[$product_id] * $quantity;
            }
        }
    } catch (PDOException $e) {
        $subtotal = 0;
    }
}

$total = $subtotal; // You can add shipping, tax calculations here

echo json_encode([
    'subtotal' => $subtotal,
    'total' => $total
]);
?>