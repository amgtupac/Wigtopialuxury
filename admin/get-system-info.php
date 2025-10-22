<?php
require_once '../app/core/db.php';
require_admin_login();

header('Content-Type: application/json');

try {
    $info = [];

    // Get database size (approximate)
    $stmt = $pdo->query("
        SELECT
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
        FROM information_schema.tables
        WHERE table_schema = 'wigshop'
    ");
    $db_size = $stmt->fetch()['size_mb'] ?? '0';
    $info['dbSize'] = $db_size . ' MB';

    // Get total tables
    $stmt = $pdo->query("
        SELECT COUNT(*) as total_tables
        FROM information_schema.tables
        WHERE table_schema = 'wigshop'
    ");
    $info['totalTables'] = $stmt->fetch()['total_tables'];

    // Get last backup time (this would need to be implemented with actual backup tracking)
    $info['lastBackup'] = 'Never'; // Placeholder - implement actual backup tracking

    echo json_encode($info);
} catch(PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
