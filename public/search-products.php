<?php
/**
 * Search Products - AJAX Endpoint for Advanced Search
 */

require_once '../app/core/db.php';

header('Content-Type: application/json');

try {
    // Get search parameters
    $query = isset($_GET['q']) ? sanitize_input($_GET['q']) : '';
    $category = isset($_GET['category']) ? sanitize_input($_GET['category']) : '';
    $minPrice = isset($_GET['minPrice']) && $_GET['minPrice'] !== '' ? (float)$_GET['minPrice'] : null;
    $maxPrice = isset($_GET['maxPrice']) && $_GET['maxPrice'] !== '' ? (float)$_GET['maxPrice'] : null;
    $inStock = isset($_GET['inStock']) && $_GET['inStock'] === '1';
    $sortBy = isset($_GET['sortBy']) ? sanitize_input($_GET['sortBy']) : 'created_at DESC';
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    // Search query
    if (!empty($query)) {
        $where_conditions[] = "(name LIKE ? OR description LIKE ? OR category LIKE ?)";
        $search_param = "%$query%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Category filter
    if (!empty($category)) {
        $where_conditions[] = "category = ?";
        $params[] = $category;
    }
    
    // Price range filter
    if ($minPrice !== null) {
        $where_conditions[] = "price >= ?";
        $params[] = $minPrice;
    }
    
    if ($maxPrice !== null) {
        $where_conditions[] = "price <= ?";
        $params[] = $maxPrice;
    }
    
    // In stock filter
    if ($inStock) {
        $where_conditions[] = "stock > 0";
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
    
    if (!in_array($sortBy, $allowed_sorts)) {
        $sortBy = 'created_at DESC';
    }
    
    // Get total count
    $count_sql = "SELECT COUNT(*) FROM products $where_clause";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total = $count_stmt->fetchColumn();
    
    // Get products
    $sql = "SELECT * FROM products $where_clause ORDER BY $sortBy";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Return JSON response
    echo json_encode([
        'success' => true,
        'products' => $products,
        'total' => $total,
        'query' => $query,
        'filters' => [
            'category' => $category,
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
            'inStock' => $inStock,
            'sortBy' => $sortBy
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Search failed',
        'message' => $e->getMessage()
    ]);
}
?>
