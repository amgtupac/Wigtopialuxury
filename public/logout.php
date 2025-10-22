<?php
require_once '../app/core/db.php';

// Clear remember me cookie if set
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, "/", "", false, true);
    
    // Remove token from database
    if (isset($_SESSION['user_id'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
        } catch (PDOException $e) {
            // Handle error silently
        }
    }
}

// Clear all session data
$_SESSION = array();

// Destroy session
session_destroy();

// Start new session for message
session_start();
$_SESSION['message'] = 'You have been logged out successfully.';

// Redirect to homepage
header("Location: index.php");
exit();
?>