<?php
/**
 * Get Categories - AJAX Endpoint
 */

require_once '../app/core/db.php';

header('Content-Type: application/json');

try {
    // Get all categories
    $stmt = $pdo->query("SELECT id, name, icon, description FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'categories' => $categories
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load categories',
        'message' => $e->getMessage()
    ]);
}
?>
