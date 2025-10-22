<?php
require_once '../app/core/db.php';

$error = '';
$success = '';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize_input($_POST['name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = sanitize_input($_POST['phone'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');

    // Server-side validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);

            if ($stmt->rowCount() > 0) {
                $error = "Email already registered. Please use a different email.";
            } else {
                // Hash password with stronger algorithm
                $hashed_password = password_hash($password, PASSWORD_ARGON2ID);

                $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, address) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $email, $hashed_password, $phone, $address]);

                $success = "Registration successful! Redirecting to homepage...";

                // Auto-login the user
                session_regenerate_id(true);
                $user_id = $pdo->lastInsertId();
                $_SESSION['user_id'] = $user_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;

                // Log user registration activity
                log_user_activity($user_id, 'register', 'New user registered: ' . $name);

                // Redirect to homepage after 2 seconds
                header("refresh:2;url=index.php");
            }
        } catch (PDOException $e) {
            $error = "Registration failed. Please try again later.";
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Elegant Wigs</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="assets/css/smooth-animations.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <?php include 'header.php'; ?>

    <main class="flex-grow flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-8 relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-pink-50 to-rose-100 opacity-50"></div>
            
            <div class="relative z-10">
                <div class="text-center mb-8">
                    <h1 class="text-3xl font-bold text-rose-600">Create Account</h1>
                    <p class="text-gray-600 mt-2">Join our premium wig collection</p>
                </div>

                <?php if ($error): ?>
                    <div class="bg-rose-50 border border-rose-200 text-rose-700 px-4 py-3 rounded-lg mb-6">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>

                <form class="space-y-6" method="POST">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                        <input type="text" id="name" name="name" required
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                               class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 transition duration-200"
                               placeholder="Enter your full name">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                        <input type="email" id="email" name="email" required
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                               class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 transition duration-200"
                               placeholder="Enter your email">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                            <input type="password" id="password" name="password" required minlength="8"
                                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 transition duration-200"
                                   placeholder="Enter your password">
                        </div>
                        <div>
                            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required
                                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 transition duration-200"
                                   placeholder="Confirm your password">
                        </div>
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="tel" id="phone" name="phone"
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                               class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 transition duration-200"
                               placeholder="Enter your phone number">
                    </div>

                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                        <textarea id="address" name="address" rows="4"
                                  class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-rose-500 focus:border-rose-500 transition duration-200"
                                  placeholder="Enter your address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                    </div>

                    <button type="submit" 
                            class="w-full bg-rose-600 text-white py-3 rounded-lg font-semibold hover:bg-rose-700 transition duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                        Create Account
                    </button>
                </form>

                <div class="text-center mt-6">
                    <p class="text-gray-600">Already have an account? 
                        <a href="login.php" class="text-rose-600 hover:text-rose-700 font-medium transition duration-200">Login here</a>
                    </p>
                </div>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('form');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const email = document.getElementById('email');

            // Real-time password confirmation validation
            confirmPassword.addEventListener('input', function() {
                if (password.value !== this.value) {
                    this.setCustomValidity('Passwords do not match');
                    this.classList.add('border-rose-500');
                } else {
                    this.setCustomValidity('');
                    this.classList.remove('border-rose-500');
                }
            });

            // Real-time email validation
            email.addEventListener('input', function() {
                if (!this.value.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                    this.setCustomValidity('Please enter a valid email address');
                    this.classList.add('border-rose-500');
                } else {
                    this.setCustomValidity('');
                    this.classList.remove('border-rose-500');
                }
            });

            // Prevent form submission if there are validation errors
            form.addEventListener('submit', (e) => {
                if (!form.checkValidity()) {
                    e.preventDefault();
                    form.reportValidity();
                }
            });
        });
    </script>
    <script src="assets/js/smooth-loader.js"></script>
</body>
</html>