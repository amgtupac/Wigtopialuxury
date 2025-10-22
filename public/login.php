<?php
require_once '../app/core/db.php';

$error = '';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']);

    // Validation
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session for security
                regenerate_session();

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['last_activity'] = time();

                // Log user login activity
                log_user_activity($user['id'], 'login', 'User logged in successfully');

                // Set remember me if checked
                if ($remember) {
                    set_remember_me($user['id']);
                }

                // Redirect to intended page or homepage
                $redirect = isset($_SESSION['redirect_after_login']) ? 
                    $_SESSION['redirect_after_login'] : 
                    (isset($_GET['redirect']) ? $_GET['redirect'] : 'index.php');
                
                // Clear the redirect after login to prevent future redirects
                unset($_SESSION['redirect_after_login']);
                
                redirect($redirect, 'Welcome back, ' . $user['name'] . '!');
            } else {
                $error = "Invalid email or password";
                // Log failed login attempt
                if ($user) {
                    log_user_activity($user['id'], 'login_failed', 'Failed login attempt');
                }
            }
        } catch (PDOException $e) {
            $error = "Login failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Elegant Wigs</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="assets/css/smooth-animations.css" rel="stylesheet">
    <style>
        /* Enhanced Login Page Styles */
        .login-container {
            max-width: 500px;
            margin: 4rem auto 2rem; /* Increased top margin to push form down */
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(220, 53, 69, 0.2);
            padding: 3rem;
            border: 2px solid rgba(220, 53, 69, 0.3);
            position: relative;
            overflow: hidden;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(220, 53, 69, 0.05) 0%, rgba(200, 35, 51, 0.1) 100%);
            pointer-events: none;
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-title {
            font-size: 2rem;
            font-weight: 700;
            color: #dc3545;
            margin-bottom: 0.5rem;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        }

        .login-subtitle {
            color: #666;
            font-size: 1rem;
            margin: 0;
        }

        .login-form .form-group {
            margin-bottom: 1.5rem;
        }

        .login-form label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2C3E50;
            font-size: 0.95rem;
        }

        .login-form input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #E1E8ED;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            box-sizing: border-box;
        }

        .login-form input:focus {
            outline: none;
            border-color: #dc3545;
            background: white;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        .login-form input::placeholder {
            color: #999;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .checkbox-group label {
            margin: 0;
            font-weight: 500;
            cursor: pointer;
        }

        .login-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1rem;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
            background: linear-gradient(135deg, #c82333 0%, #a71e2a 100%);
        }

        .login-links {
            text-align: center;
            margin-top: 1.5rem;
        }

        .login-links a {
            color: #dc3545;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .login-links a:hover {
            color: #c82333;
        }

        .login-links .separator {
            color: #999;
            margin: 0 0.5rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.95rem;
        }

        .alert-error {
            background: linear-gradient(135deg, #F8D7DA 0%, #FDE2E4 100%);
            color: #721C24;
            border: 1px solid rgba(220, 53, 69, 0.2);
        }

        .alert-success {
            background: linear-gradient(135deg, #D4EDDA 0%, #E8F5E8 100%);
            color: #155724;
            border: 1px solid rgba(40, 167, 69, 0.2);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .login-container {
                margin: 1rem;
                padding: 2rem 1.5rem;
            }

            .login-title {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 1.5rem 1rem;
            }

            .login-form input {
                padding: 0.8rem;
            }

            .login-btn {
                padding: 0.8rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <main>
        <div class="container">
            <div class="login-container">
                <div class="login-header">
                    <h1 class="login-title">Welcome Back</h1>
                    <p class="login-subtitle">Login to your account to continue shopping</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form class="login-form" method="POST">
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               placeholder="Enter your email">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required
                               placeholder="Enter your password">
                    </div>

                    <div class="checkbox-group">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>

                    <button type="submit" class="login-btn">Login</button>
                </form>

                <div class="login-links">
                    <p>Don't have an account? <a href="register.php">Sign up here</a></p>
                    <p><a href="#" style="color: #666;">Forgot your password?</a></p>
                </div>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
    <script src="assets/js/smooth-loader.js"></script>
</body>
</html>