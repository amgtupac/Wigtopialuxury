/**
 * Infinite Scroll for Products
 * Automatically loads more products as user scrolls down
 */

(function() {
    'use strict';

    class InfiniteScroll {
        constructor(options = {}) {
            this.container = options.container || '#productContainer';
            this.loadMoreUrl = options.loadMoreUrl || 'load-more-products.php';
            this.threshold = options.threshold || 300; // pixels from bottom
            this.page = options.startPage || 1;
            this.perPage = options.perPage || 12;
            this.loading = false;
            this.hasMore = true;
            this.filters = options.filters || {};
            
            this.init();
        }

        init() {
            this.containerElement = document.querySelector(this.container);
            
            if (!this.containerElement) {
                console.warn('Infinite scroll container not found');
                return;
            }

            // Create loading indicator
            this.createLoadingIndicator();
            
            // Attach scroll event
            window.addEventListener('scroll', this.handleScroll.bind(this));
            
            // Initial check in case content is short
            setTimeout(() => this.checkScroll(), 100);
        }

        createLoadingIndicator() {
            this.loader = document.createElement('div');
            this.loader.id = 'infiniteScrollLoader';
            this.loader.className = 'text-center py-8 hidden';
            this.loader.innerHTML = `
                <div class="inline-block">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-amber-600"></div>
                    <p class="mt-2 text-gray-600">Loading more products...</p>
                </div>
            `;
            
            // Insert after container
            this.containerElement.parentNode.insertBefore(
                this.loader,
                this.containerElement.nextSibling
            );
        }

        handleScroll() {
            if (this.loading || !this.hasMore) return;
            
            this.checkScroll();
        }

        checkScroll() {
            const scrollPosition = window.innerHeight + window.pageYOffset;
            const pageHeight = document.documentElement.scrollHeight;
            
            if (pageHeight - scrollPosition < this.threshold) {
                this.loadMore();
            }
        }

        async loadMore() {
            if (this.loading || !this.hasMore) return;
            
            this.loading = true;
            this.showLoader();
            
            try {
                this.page++;
                
                // Build query parameters
                const params = new URLSearchParams({
                    page: this.page,
                    per_page: this.perPage,
                    ...this.filters
                });
                
                const response = await fetch(`${this.loadMoreUrl}?${params}`);
                const data = await response.json();
                
                if (data.success && data.products && data.products.length > 0) {
                    this.appendProducts(data.products);
                    
                    // Check if there are more products
                    if (data.products.length < this.perPage || data.hasMore === false) {
                        this.hasMore = false;
                        this.showEndMessage();
                    }
                } else {
                    this.hasMore = false;
                    this.showEndMessage();
                }
                
            } catch (error) {
                console.error('Error loading more products:', error);
                this.showError();
            } finally {
                this.loading = false;
                this.hideLoader();
            }
        }

        appendProducts(products) {
            const fragment = document.createDocumentFragment();
            
            products.forEach(product => {
                const productCard = this.createProductCard(product);
                fragment.appendChild(productCard);
            });
            
            this.containerElement.appendChild(fragment);
            
            // Trigger animation
            const newCards = this.containerElement.querySelectorAll('.product-card:not(.animated)');
            newCards.forEach((card, index) => {
                card.classList.add('animated');
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    card.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 100);
            });
        }

        createProductCard(product) {
            const card = document.createElement('div');
            card.className = 'product-card bg-white rounded-xl shadow-md overflow-hidden hover-scale';
            
            // Get main image
            const images = product.images ? product.images.split(',') : [];
            const mainImageIndex = product.main_image_index || 0;
            const mainImage = images[mainImageIndex] || images[0] || 'default.jpg';
            
            card.innerHTML = `
                <a href="product-details.php?id=${product.id}" class="block">
                    <div class="relative">
                        <img src="uploads/images/${mainImage.trim()}" 
                             alt="${this.escapeHtml(product.name)}" 
                             class="w-full h-48 object-cover"
                             loading="lazy">
                        ${product.featured ? '<span class="absolute top-2 right-2 bg-amber-500 text-white text-xs px-2 py-1 rounded-full">Featured</span>' : ''}
                        ${product.stock <= 5 && product.stock > 0 ? '<span class="absolute top-2 left-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full">Low Stock</span>' : ''}
                        ${product.stock === 0 ? '<span class="absolute top-2 left-2 bg-gray-500 text-white text-xs px-2 py-1 rounded-full">Out of Stock</span>' : ''}
                    </div>
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-gray-800 mb-2 truncate">${this.escapeHtml(product.name)}</h3>
                        <p class="text-gray-600 text-sm mb-3 line-clamp-2">${this.escapeHtml(product.description || '')}</p>
                        <div class="flex items-center justify-between">
                            <span class="text-2xl font-bold text-amber-600">$${parseFloat(product.price).toFixed(2)}</span>
                            <span class="text-sm text-gray-500">${product.category}</span>
                        </div>
                        ${product.stock > 0 ? `
                            <button class="mt-3 w-full bg-amber-600 text-white py-2 px-4 rounded-lg hover:bg-amber-700 transition-colors add-to-cart" 
                                    data-product-id="${product.id}">
                                Add to Cart
                            </button>
                        ` : `
                            <button class="mt-3 w-full bg-gray-400 text-white py-2 px-4 rounded-lg cursor-not-allowed" disabled>
                                Out of Stock
                            </button>
                        `}
                    </div>
                </a>
            `;
            
            return card;
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        showLoader() {
            this.loader.classList.remove('hidden');
        }

        hideLoader() {
            this.loader.classList.add('hidden');
        }

        showEndMessage() {
            if (!document.getElementById('infiniteScrollEnd')) {
                const endMessage = document.createElement('div');
                endMessage.id = 'infiniteScrollEnd';
                endMessage.className = 'text-center py-8 text-gray-600';
                endMessage.innerHTML = `
                    <p class="text-lg">✨ You've reached the end!</p>
                    <p class="text-sm mt-2">No more products to load</p>
                `;
                this.loader.parentNode.insertBefore(endMessage, this.loader.nextSibling);
            }
        }

        showError() {
            const errorMessage = document.createElement('div');
            errorMessage.className = 'text-center py-8 text-red-600';
            errorMessage.innerHTML = `
                <p>Failed to load more products</p>
                <button onclick="location.reload()" class="mt-2 text-amber-600 hover:underline">Reload Page</button>
            `;
            this.loader.parentNode.insertBefore(errorMessage, this.loader.nextSibling);
        }

        updateFilters(newFilters) {
            this.filters = { ...this.filters, ...newFilters };
            this.reset();
        }

        reset() {
            this.page = 1;
            this.hasMore = true;
            this.loading = false;
            
            // Remove end message if exists
            const endMessage = document.getElementById('infiniteScrollEnd');
            if (endMessage) {
                endMessage.remove();
            }
        }

        destroy() {
            window.removeEventListener('scroll', this.handleScroll.bind(this));
            if (this.loader) {
                this.loader.remove();
            }
        }
    }

    // Expose to global scope
    window.InfiniteScroll = InfiniteScroll;

})();
