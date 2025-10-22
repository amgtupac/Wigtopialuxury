<?php
// Database update script for session security features
// Run this script once to add remember_token columns to existing databases

require_once 'app/core/db.php';

try {
    // Add remember_token and remember_token_expires columns to users table
    $sql = "ALTER TABLE users
            ADD COLUMN remember_token VARCHAR(255) DEFAULT NULL,
            ADD COLUMN remember_token_expires DATETIME DEFAULT NULL,
            ADD KEY remember_token (remember_token)";

    $pdo->exec($sql);
    echo "✅ Successfully added remember_token columns to users table!\n";

    // Update existing users to have NULL values for new columns
    $sql = "UPDATE users SET remember_token = NULL, remember_token_expires = NULL WHERE remember_token IS NULL";
    $pdo->exec($sql);
    echo "✅ Updated existing user records!\n";

    echo "🎉 Database update completed successfully!\n";
    echo "Your session security features are now ready to use.\n";

} catch (PDOException $e) {
    echo "❌ Error updating database: " . $e->getMessage() . "\n";
    echo "You may need to run this manually or the columns already exist.\n";
}
?>
