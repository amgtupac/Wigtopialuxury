// Cart functionality with animated popups
document.addEventListener('DOMContentLoaded', function() {
    // Create notification container if it doesn't exist
    if (!document.getElementById('notification-container')) {
        const container = document.createElement('div');
        container.id = 'notification-container';
        container.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(container);
    }

    // Function to show animated notifications
    function showNotification(message, type = 'success', duration = 4000) {
        const notification = document.createElement('div');
        notification.className = `notification-${type} max-w-sm w-full bg-white border-l-4 p-4 rounded-lg shadow-lg transform translate-x-full opacity-0 transition-all duration-300 ease-in-out`;
        
        if (type === 'success') {
            notification.classList.add('border-green-500');
        } else if (type === 'error') {
            notification.classList.add('border-red-500');
        } else if (type === 'warning') {
            notification.classList.add('border-yellow-500');
        }

        notification.innerHTML = `
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    ${type === 'success' ? '✅' : type === 'error' ? '❌' : '⚠️'}
                </div>
                <div class="ml-3 flex-1">
                    <p class="text-sm font-medium text-gray-800">${message}</p>
                </div>
                <div class="ml-4 flex-shrink-0">
                    <button class="notification-close text-gray-400 hover:text-gray-600 focus:outline-none focus:text-gray-600">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                        </svg>
                    </button>
                </div>
            </div>
        `;

        document.getElementById('notification-container').appendChild(notification);

        // Trigger animation
        setTimeout(() => {
            notification.classList.remove('translate-x-full', 'opacity-0');
        }, 10);

        // Auto remove after duration
        const autoRemove = setTimeout(() => {
            removeNotification(notification);
        }, duration);

        // Manual close button
        notification.querySelector('.notification-close').addEventListener('click', () => {
            clearTimeout(autoRemove);
            removeNotification(notification);
        });

        // Update cart count in header
        updateCartCount();
    }

    function removeNotification(notification) {
        notification.classList.add('translate-x-full', 'opacity-0');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    // Function to make cart API calls
    async function cartAction(action, productId, quantity = 1) {
        try {
            const response = await fetch('cart-handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: action,
                    product_id: productId,
                    quantity: quantity
                })
            });

            const data = await response.json();

            if (data.success) {
                // Use appropriate message based on action
                let message = '';
                switch(action) {
                    case 'add':
                        message = 'Product added to cart successfully!';
                        break;
                    case 'update':
                        message = 'Cart updated successfully!';
                        break;
                    case 'remove':
                        message = 'Product removed from cart!';
                        break;
                    default:
                        message = 'Action completed successfully!';
                }
                showNotification(message, 'success');
                refreshIfCartPage(); // Refresh cart page if needed
            } else {
                showNotification(data.message || 'An error occurred. Please try again.', 'error');
            }

            return data;
        } catch (error) {
            showNotification('An error occurred. Please try again.', 'error');
            console.error('Cart action error:', error);
        }
    }

    // Function to update cart count in header
    async function updateCartCount() {
        try {
            const response = await fetch('get-cart-count.php');
            const data = await response.json();
            
            const cartCountElements = document.querySelectorAll('.cart-count');
            cartCountElements.forEach(element => {
                element.textContent = data.count || 0;
                element.style.display = data.count > 0 ? 'inline' : 'none';
            });
        } catch (error) {
            console.error('Error updating cart count:', error);
        }
    }

    // Add to Cart buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('add-to-cart') || e.target.closest('.add-to-cart')) {
            e.preventDefault();
            const button = e.target.classList.contains('add-to-cart') ? e.target : e.target.closest('.add-to-cart');
            const productId = button.getAttribute('data-product-id');
            
            if (productId) {
                cartAction('add', parseInt(productId));
            }
        }
    });

    // Cart page quantity controls
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('quantity-btn')) {
            e.preventDefault();
            const button = e.target;
            const action = button.getAttribute('data-action');
            const productId = button.getAttribute('data-product-id');
            
            if (productId && action) {
                const quantityElement = button.parentNode.querySelector('.quantity-value');
                let currentQuantity = parseInt(quantityElement.textContent);
                
                if (action === 'increase') {
                    cartAction('update', parseInt(productId), currentQuantity + 1);
                } else if (action === 'decrease' && currentQuantity > 1) {
                    cartAction('update', parseInt(productId), currentQuantity - 1);
                }
            }
        }
    });

    // Remove from cart buttons
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-from-cart') || e.target.closest('.remove-from-cart')) {
            e.preventDefault();
            const button = e.target.classList.contains('remove-from-cart') ? e.target : e.target.closest('.remove-from-cart');
            const productId = button.getAttribute('data-product-id');
            
            if (productId) {
                if (confirm('Are you sure you want to remove this item from your cart?')) {
                    cartAction('remove', parseInt(productId));
                    // Remove the row from the table after a short delay
                    setTimeout(() => {
                        const row = button.closest('tr');
                        if (row) {
                            row.style.transition = 'opacity 0.3s ease';
                            row.style.opacity = '0';
                            setTimeout(() => row.remove(), 300);
                        }
                    }, 500);
                }
            }
        }
    });

    // Function to refresh current page if it's the cart page
    function refreshIfCartPage() {
        if (window.location.pathname.includes('cart.php')) {
            setTimeout(() => {
                location.reload();
            }, 1000); // Refresh after showing notification
        }
    }
});
