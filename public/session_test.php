<?php
require_once '../app/core/db.php';

// Test script to verify session security is working
echo "<h2>🔒 Session Security Test</h2>\n";

// Check if user is logged in
if (is_logged_in()) {
    echo "<p>✅ <strong>You are logged in!</strong></p>\n";
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>\n";
    echo "<p>User Name: " . $_SESSION['user_name'] . "</p>\n";
    echo "<p>Last Activity: " . (isset($_SESSION['last_activity']) ? date('Y-m-d H:i:s', $_SESSION['last_activity']) : 'Not set') . "</p>\n";

    // Show session timeout info
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        $remaining_time = SESSION_TIMEOUT - $inactive_time;
        echo "<p>Session expires in: " . gmdate('i:s', $remaining_time) . " minutes</p>\n";
    }

    echo "<p><a href='logout.php'>Logout</a></p>\n";
} else {
    echo "<p>❌ <strong>You are not logged in</strong></p>\n";
    echo "<p><a href='login.php'>Login</a></p>\n";
}

// Check for remember me cookie
if (isset($_COOKIE['remember_token'])) {
    echo "<p>🍪 Remember me cookie is set</p>\n";
} else {
    echo "<p>🍪 No remember me cookie</p>\n";
}

// Session info
echo "<h3>Session Information:</h3>\n";
echo "<p>Session ID: " . session_id() . "</p>\n";
echo "<p>Session Status: " . (session_status() == PHP_SESSION_ACTIVE ? 'Active' : 'Inactive') . "</p>\n";
echo "<p>Session Timeout: " . SESSION_TIMEOUT . " seconds (" . (SESSION_TIMEOUT / 60) . " minutes)</p>\n";

echo "<h3>Security Settings:</h3>\n";
echo "<p>Cookie HTTP Only: " . (ini_get('session.cookie_httponly') ? '✅ Enabled' : '❌ Disabled') . "</p>\n";
echo "<p>Cookie Secure: " . (ini_get('session.cookie_secure') ? '✅ Enabled' : '⚠️ Disabled (OK for HTTP)') . "</p>\n";
echo "<p>Cookie SameSite: " . ini_get('session.cookie_samesite') . "</p>\n";
?>
