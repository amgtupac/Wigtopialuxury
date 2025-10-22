<?php
require_once '../app/core/db.php';

// Check if admin is logged in
if (is_admin_logged_in()) {
    // Log admin logout activity
    $admin_id = $_SESSION['admin_id'];
    try {
        $stmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, action, timestamp) VALUES (?, 'logout', NOW())");
        $stmt->execute([$admin_id]);
    } catch(PDOException $e) {
        // Log error but don't stop logout process
        error_log("Failed to log admin logout: " . $e->getMessage());
    }

    // Clear admin session
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_name']);
    unset($_SESSION['admin_last_activity']);
}

// Redirect to admin login
header('Location: login.php');
exit();
?>
