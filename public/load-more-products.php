<?php
/**
 * Load More Products - AJAX Endpoint for Infinite Scroll
 */

require_once '../app/core/db.php';

header('Content-Type: application/json');

try {
    // Get parameters
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 12;
    $category = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
    $search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';
    $sort = isset($_GET['sort']) ? sanitize_input($_GET['sort']) : 'created_at DESC';
    
    // Calculate offset
    $offset = ($page - 1) * $per_page;
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    if (!empty($category) && $category !== 'all') {
        $where_conditions[] = "category = ?";
        $params[] = $category;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(name LIKE ? OR description LIKE ? OR category LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Validate sort parameter
    $allowed_sorts = [
        'created_at DESC',
        'created_at ASC',
        'price ASC',
        'price DESC',
        'name ASC',
        'name DESC'
    ];
    
    if (!in_array($sort, $allowed_sorts)) {
        $sort = 'created_at DESC';
    }
    
    // Get products
    $sql = "SELECT * FROM products $where_clause ORDER BY $sort LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    
    // Add limit and offset to params
    $params[] = $per_page;
    $params[] = $offset;
    
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if there are more products
    $count_sql = "SELECT COUNT(*) FROM products $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute(array_slice($params, 0, -2)); // Remove limit and offset
    $total_products = $count_stmt->fetchColumn();
    
    $has_more = ($offset + $per_page) < $total_products;
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'products' => $products,
        'page' => $page,
        'per_page' => $per_page,
        'total' => $total_products,
        'hasMore' => $has_more
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load products',
        'message' => $e->getMessage()
    ]);
}
?>
