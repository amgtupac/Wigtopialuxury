<?php
require_once '../app/core/db.php';
require_admin_login();

header('Content-Type: application/json');

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if (!$order_id) {
    echo json_encode(['error' => 'Invalid order ID']);
    exit();
}

try {
    // Get order details with items
    $stmt = $pdo->prepare("
        SELECT oi.*, p.name as product_name, p.images
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();

    echo json_encode(['success' => true, 'items' => $items]);

} catch(PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
