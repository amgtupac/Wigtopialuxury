<?php
require_once '../app/core/db.php';
require_admin_login();

header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Clear all admin activity logs
        $stmt = $pdo->exec("DELETE FROM admin_activity_log");

        // Log this action
        $stmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, action, details) VALUES (?, 'activity_log_cleared', 'All admin activity logs cleared')");
        $stmt->execute([$_SESSION['admin_id']]);

        $response['success'] = true;
        $response['message'] = 'Activity log cleared successfully';
    } catch(PDOException $e) {
        $response['error'] = 'Error clearing activity log: ' . $e->getMessage();
    }
}

echo json_encode($response);
?>
