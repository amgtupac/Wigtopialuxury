<?php
// Helper function to get user initials
function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
    }
    return substr($initials, 0, 2); // Limit to 2 initials
}

// Get cart count
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $cart_count = array_sum($_SESSION['cart']);
}
?>
<nav class="sticky top-4 z-50 mx-auto max-w-7xl bg-gradient-to-r from-amber-900/90 to-amber-800/90 text-white shadow-xl backdrop-blur-md rounded-3xl my-4">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between py-4">
        <!-- Left Section: Logo and Nav Links -->
        <div class="flex items-center space-x-4">
            <a href="index.php" class="flex items-center space-x-2">
                <img src="assets/images/logo img.png" alt="Wigtopia" class="h-10 w-auto">
                <span class="text-xl font-bold tracking-tight">Wigtopia</span>
            </a>
            <!-- Desktop Nav Links -->
            <ul class="hidden lg:flex items-center space-x-6 ml-8">
                <li><a href="index.php" class="bg-transparent border border-white text-white py-2 px-4 rounded-full hover:bg-amber-300 hover:text-amber-900 hover:border-amber-300 transition-all duration-300">Home</a></li>
                <li><a href="products.php" class="bg-transparent border border-white text-white py-2 px-4 rounded-full hover:bg-amber-300 hover:text-amber-900 hover:border-amber-300 transition-all duration-300">Products</a></li>
            </ul>
        </div>

        <!-- Right Section: Cart and User Actions -->
        <div class="flex items-center space-x-4">
            <a href="cart.php" class="relative text-white hover:text-amber-300 transition-colors duration-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                </svg>
                <span class="cart-count absolute -top-2 -right-2 bg-amber-500 text-white text-xs font-bold rounded-full px-2 py-1" style="display: <?php echo $cart_count == 0 ? 'none' : 'inline'; ?>;">
                    <?php echo $cart_count; ?>
                </span>
            </a>
            <div class="hidden lg:flex items-center space-x-4">
                <?php if (is_logged_in()): ?>
                    <a href="accounts.php" class="bg-transparent border border-white text-white py-2 px-4 rounded-full hover:bg-amber-300 hover:text-amber-900 hover:border-amber-300 transition-all duration-300"><?php echo isset($_SESSION['user_name']) ? getInitials($_SESSION['user_name']) : 'Account'; ?></a>
                    <a href="logout.php" class="bg-amber-600 text-white py-2 px-4 rounded-full hover:bg-amber-700 transition-all duration-300">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="bg-transparent border border-white text-white py-2 px-4 rounded-full hover:bg-amber-300 hover:text-amber-900 hover:border-amber-300 transition-all duration-300">Login</a>
                    <a href="register.php" class="bg-amber-600 text-white py-2 px-4 rounded-full hover:bg-amber-700 transition-all duration-300">Sign Up</a>
                <?php endif; ?>
            </div>
            <!-- Mobile Menu Toggle -->
            <button class="lg:hidden text-white text-2xl" id="mobileNavToggle" aria-label="Toggle menu">
                <span id="menuIcon">☰</span>
            </button>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div class="lg:hidden hidden" id="navLinks">
        <ul class="bg-gradient-to-b from-amber-900/95 to-amber-800/95 backdrop-blur-md px-6 py-8 space-y-6 rounded-b-3xl shadow-2xl border-t border-amber-700/50">
            <li>
                <a href="index.php" class="flex items-center text-white hover:text-amber-200 hover:bg-amber-800/50 text-lg py-3 px-2 rounded-lg transition-all duration-300">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    Home
                </a>
            </li>
            <li>
                <a href="products.php" class="flex items-center justify-between text-white hover:text-amber-200 hover:bg-amber-800/50 text-lg py-3 px-2 rounded-lg transition-all duration-300" id="dropdownToggle">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                        Products
                    </div>
                    <svg class="w-5 h-5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </a>
                <ul class="hidden pl-8 space-y-3 mt-3" id="dropdownMenu">
                    <li><a href="products.php?category=Wigs" class="block text-amber-200 hover:text-amber-100 py-2 pl-4 border-l-2 border-amber-600 transition-colors">Wigs</a></li>
                    <li><a href="products.php?category=Hair Extensions" class="block text-amber-200 hover:text-amber-100 py-2 pl-4 border-l-2 border-amber-600 transition-colors">Extensions</a></li>
                    <li><a href="products.php?category=Accessories" class="block text-amber-200 hover:text-amber-100 py-2 pl-4 border-l-2 border-amber-600 transition-colors">Accessories</a></li>
                </ul>
            </li>
            <?php if (is_logged_in()): ?>
                <li>
                    <a href="accounts.php" class="flex items-center justify-center bg-amber-600 hover:bg-amber-500 text-white text-lg py-3 px-4 rounded-full shadow-lg transition-all duration-300 transform hover:scale-105">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <?php echo isset($_SESSION['user_name']) ? getInitials($_SESSION['user_name']) : 'Account'; ?>
                    </a>
                </li>
                <li>
                    <a href="logout.php" class="flex items-center text-white hover:text-amber-200 hover:bg-amber-800/50 text-lg py-3 px-2 rounded-lg transition-all duration-300">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Logout
                    </a>
                </li>
            <?php else: ?>
                <li>
                    <a href="login.php" class="flex items-center text-white hover:text-amber-200 hover:bg-amber-800/50 text-lg py-3 px-2 rounded-lg transition-all duration-300">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                        Login
                    </a>
                </li>
                <li>
                    <a href="register.php" class="flex items-center bg-amber-600 hover:bg-amber-500 text-white text-lg py-3 px-4 rounded-lg shadow-lg transition-all duration-300 transform hover:scale-105">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                        </svg>
                        Sign Up
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<script src="https://cdn.tailwindcss.com"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const mobileNavToggle = document.getElementById('mobileNavToggle');
    const navLinks = document.getElementById('navLinks');
    const menuIcon = document.getElementById('menuIcon');
    const dropdownToggle = document.getElementById('dropdownToggle');
    const dropdownMenu = document.getElementById('dropdownMenu');

    mobileNavToggle.addEventListener('click', () => {
        navLinks.classList.toggle('hidden');
        menuIcon.textContent = navLinks.classList.contains('hidden') ? '☰' : '✕';
    });

    dropdownToggle.addEventListener('click', (e) => {
        e.preventDefault();
        dropdownMenu.classList.toggle('hidden');
        // Rotate arrow icon
        const arrowIcon = dropdownToggle.querySelector('svg');
        if (dropdownMenu.classList.contains('hidden')) {
            arrowIcon.style.transform = 'rotate(0deg)';
        } else {
            arrowIcon.style.transform = 'rotate(180deg)';
        }
    });

    // Close dropdown and mobile menu when clicking outside
    document.addEventListener('click', (e) => {
        if (!dropdownToggle.contains(e.target) && !dropdownMenu.contains(e.target)) {
            dropdownMenu.classList.add('hidden');
        }
        if (!mobileNavToggle.contains(e.target) && !navLinks.contains(e.target)) {
            navLinks.classList.add('hidden');
            menuIcon.textContent = '☰';
        }
    });
});
</script>