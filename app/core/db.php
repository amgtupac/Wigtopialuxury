<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'wigshop');

// Session timeout in seconds (30 minutes)
define('SESSION_TIMEOUT', 30 * 60);
// Remember me token timeout in seconds (30 days)
define('REMEMBER_TIMEOUT', 30 * 24 * 60 * 60);

try {
    // Create PDO connection
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Configure secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', SESSION_TIMEOUT);
ini_set('session.cookie_lifetime', 0); // Session cookie expires when browser closes

// Function to sanitize input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to redirect with message
function redirect($url, $message = '') {
    if($message) {
        $_SESSION['message'] = $message;
    }
    header("Location: $url");
    exit();
}

// Function to check if user is logged in (with timeout check)
function is_logged_in() {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        check_remember_me();
    }

    // Check if session exists
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Check if session has timed out
    if (isset($_SESSION['last_activity'])) {
        $inactive_time = time() - $_SESSION['last_activity'];
        if ($inactive_time > SESSION_TIMEOUT) {
            // Session expired, destroy it only if session is active
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            return false;
        }
    }

    // Update last activity time
    $_SESSION['last_activity'] = time();

    return true;
}

// Function to check if admin is logged in (with timeout check)
function is_admin_logged_in() {
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        check_remember_me();
    }

    // Check if session exists
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }

    // Check if session has timed out
    if (isset($_SESSION['admin_last_activity'])) {
        $inactive_time = time() - $_SESSION['admin_last_activity'];
        if ($inactive_time > SESSION_TIMEOUT) {
            // Session expired, destroy it only if session is active
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_destroy();
            }
            return false;
        }
    }

    // Update last activity time
    $_SESSION['admin_last_activity'] = time();

    return true;
}

// Function to require user login
function require_login() {
    if (!is_logged_in()) {
        // Store current URL for redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect('login.php', 'Please login to continue');
    }
}

// Function to require admin login
function require_admin_login() {
    if (!is_admin_logged_in()) {
        redirect('admin/login.php', 'Please login as admin');
    }
}

// Function to regenerate session ID for security
function regenerate_session() {
    session_regenerate_id(true);
    $_SESSION['last_activity'] = time();

    // Also update admin activity if admin is logged in
    if (isset($_SESSION['admin_id'])) {
        $_SESSION['admin_last_activity'] = time();
    }
}

// Function to check and restore remember me token
function check_remember_me() {
    if (!is_logged_in() && isset($_COOKIE['remember_token'])) {
        $token = $_COOKIE['remember_token'];

        try {
            $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE remember_token = ? AND remember_token_expires > NOW()");
            $stmt->execute([$token]);
            $user = $stmt->fetch();

            if ($user) {
                // Valid remember token, restore session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['last_activity'] = time();

                // Regenerate session for security
                regenerate_session();

                // Redirect to remove token from URL
                $redirect = isset($_SESSION['redirect_after_login']) ?
                    $_SESSION['redirect_after_login'] :
                    'index.php';
                unset($_SESSION['redirect_after_login']);

                header("Location: $redirect");
                exit();
            } else {
                // Invalid or expired token, remove cookie
                setcookie('remember_token', '', time() - 3600, "/");
            }
        } catch (PDOException $e) {
            // Handle error silently
        }
    }
}

// Function to set remember me cookie and database token
function set_remember_me($user_id) {
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + REMEMBER_TIMEOUT);

    // Set cookie (30 days)
    setcookie('remember_token', $token, time() + REMEMBER_TIMEOUT, "/", "", false, true);

    // Store token in database with expiration
    try {
        $stmt = $pdo->prepare("UPDATE users SET remember_token = ?, remember_token_expires = ? WHERE id = ?");
        $stmt->execute([$token, $expires, $user_id]);
    } catch (PDOException $e) {
        // Handle error silently
    }
}

// Function to log user activity
function log_user_activity($user_id, $action, $details = null) {
    global $pdo;
    
    try {
        // Get IP address
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Get user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Insert activity log
        $stmt = $pdo->prepare("INSERT INTO user_activity_log (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $details, $ip_address, $user_agent]);
        
        return true;
    } catch (PDOException $e) {
        // Log error silently, don't break the application
        error_log("User activity log error: " . $e->getMessage());
        return false;
    }
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();

    // Check for remember me token on session start
    check_remember_me();
}
?>