<?php
require_once '../app/core/db.php';
require_admin_login();

header('Content-Type: application/json');

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$status = isset($_POST['status']) ? sanitize_input($_POST['status']) : '';

if (!$order_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID or status']);
    exit();
}

// Validate status
$valid_statuses = ['Pending', 'Processing', 'Delivered', 'Cancelled'];
if (!in_array($status, $valid_statuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit();
}

try {
    // Update order status
    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $order_id]);

    // Log admin activity
    $stmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, action, details, timestamp) VALUES (?, 'order_status_update', ?, NOW())");
    $stmt->execute([$_SESSION['admin_id'], "Order #{$order_id} status changed to {$status}"]);

    echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);

} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
