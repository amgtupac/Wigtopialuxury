/**
 * Advanced Search and Filter System
 * Provides real-time search and filtering for products
 */

(function() {
    'use strict';

    class AdvancedSearch {
        constructor(options = {}) {
            this.searchInput = options.searchInput || '#searchInput';
            this.filterContainer = options.filterContainer || '#filterContainer';
            this.resultsContainer = options.resultsContainer || '#searchResults';
            this.minChars = options.minChars || 2;
            this.debounceDelay = options.debounceDelay || 300;
            this.searchUrl = options.searchUrl || 'search-products.php';
            
            this.debounceTimer = null;
            this.currentFilters = {
                category: '',
                minPrice: '',
                maxPrice: '',
                inStock: false,
                sortBy: 'created_at DESC'
            };
            
            this.init();
        }

        init() {
            this.attachEventListeners();
            this.createFilterUI();
        }

        attachEventListeners() {
            const searchInput = document.querySelector(this.searchInput);
            
            if (searchInput) {
                // Real-time search with debounce
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(this.debounceTimer);
                    this.debounceTimer = setTimeout(() => {
                        this.performSearch(e.target.value);
                    }, this.debounceDelay);
                });

                // Search on Enter key
                searchInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        clearTimeout(this.debounceTimer);
                        this.performSearch(e.target.value);
                    }
                });
            }

            // Filter change events
            document.addEventListener('change', (e) => {
                if (e.target.matches('.filter-select, .filter-checkbox, .filter-input')) {
                    this.updateFilters();
                }
            });
        }

        createFilterUI() {
            const container = document.querySelector(this.filterContainer);
            if (!container) return;

            container.innerHTML = `
                <div class="filter-panel bg-white rounded-lg shadow-md p-4 mb-6">
                    <h3 class="text-lg font-semibold mb-4 text-gray-800">
                        <i class="fas fa-filter"></i> Filters
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Category Filter -->
                        <div class="filter-group">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                            <select class="filter-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500" 
                                    id="categoryFilter">
                                <option value="">All Categories</option>
                            </select>
                        </div>

                        <!-- Price Range -->
                        <div class="filter-group">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Min Price</label>
                            <input type="number" 
                                   class="filter-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500" 
                                   id="minPriceFilter" 
                                   placeholder="$0" 
                                   min="0" 
                                   step="0.01">
                        </div>

                        <div class="filter-group">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Max Price</label>
                            <input type="number" 
                                   class="filter-input w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500" 
                                   id="maxPriceFilter" 
                                   placeholder="$1000" 
                                   min="0" 
                                   step="0.01">
                        </div>

                        <!-- Sort By -->
                        <div class="filter-group">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                            <select class="filter-select w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500" 
                                    id="sortByFilter">
                                <option value="created_at DESC">Newest First</option>
                                <option value="created_at ASC">Oldest First</option>
                                <option value="price ASC">Price: Low to High</option>
                                <option value="price DESC">Price: High to Low</option>
                                <option value="name ASC">Name: A to Z</option>
                                <option value="name DESC">Name: Z to A</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 flex items-center justify-between">
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" 
                                   class="filter-checkbox w-4 h-4 text-amber-600 border-gray-300 rounded focus:ring-amber-500" 
                                   id="inStockFilter">
                            <span class="ml-2 text-sm text-gray-700">In Stock Only</span>
                        </label>

                        <button class="text-sm text-amber-600 hover:text-amber-700 font-medium" 
                                id="clearFiltersBtn">
                            <i class="fas fa-times-circle"></i> Clear Filters
                        </button>
                    </div>
                </div>
            `;

            // Load categories
            this.loadCategories();

            // Clear filters button
            document.getElementById('clearFiltersBtn')?.addEventListener('click', () => {
                this.clearFilters();
            });
        }

        async loadCategories() {
            try {
                const response = await fetch('get-categories.php');
                const data = await response.json();
                
                if (data.success && data.categories) {
                    const select = document.getElementById('categoryFilter');
                    if (select) {
                        data.categories.forEach(cat => {
                            const option = document.createElement('option');
                            option.value = cat.name;
                            option.textContent = cat.name;
                            select.appendChild(option);
                        });
                    }
                }
            } catch (error) {
                console.error('Error loading categories:', error);
            }
        }

        updateFilters() {
            this.currentFilters = {
                category: document.getElementById('categoryFilter')?.value || '',
                minPrice: document.getElementById('minPriceFilter')?.value || '',
                maxPrice: document.getElementById('maxPriceFilter')?.value || '',
                inStock: document.getElementById('inStockFilter')?.checked || false,
                sortBy: document.getElementById('sortByFilter')?.value || 'created_at DESC'
            };

            const searchInput = document.querySelector(this.searchInput);
            const searchQuery = searchInput ? searchInput.value : '';
            
            this.performSearch(searchQuery);
        }

        clearFilters() {
            document.getElementById('categoryFilter').value = '';
            document.getElementById('minPriceFilter').value = '';
            document.getElementById('maxPriceFilter').value = '';
            document.getElementById('inStockFilter').checked = false;
            document.getElementById('sortByFilter').value = 'created_at DESC';
            
            this.currentFilters = {
                category: '',
                minPrice: '',
                maxPrice: '',
                inStock: false,
                sortBy: 'created_at DESC'
            };

            const searchInput = document.querySelector(this.searchInput);
            if (searchInput) {
                searchInput.value = '';
            }

            this.performSearch('');
        }

        async performSearch(query) {
            const resultsContainer = document.querySelector(this.resultsContainer);
            
            if (!resultsContainer) {
                console.warn('Results container not found');
                return;
            }

            // Show loading skeleton
            if (window.SkeletonLoader) {
                window.SkeletonLoader.showProductSkeleton(resultsContainer, 8);
            }

            try {
                const params = new URLSearchParams({
                    q: query,
                    category: this.currentFilters.category,
                    minPrice: this.currentFilters.minPrice,
                    maxPrice: this.currentFilters.maxPrice,
                    inStock: this.currentFilters.inStock ? '1' : '0',
                    sortBy: this.currentFilters.sortBy
                });

                const response = await fetch(`${this.searchUrl}?${params}`);
                const data = await response.json();

                if (data.success) {
                    this.displayResults(data.products, query);
                    this.updateResultsCount(data.total);
                } else {
                    this.displayError(data.error || 'Search failed');
                }
            } catch (error) {
                console.error('Search error:', error);
                this.displayError('Failed to perform search');
            }
        }

        displayResults(products, query) {
            const container = document.querySelector(this.resultsContainer);
            if (!container) return;

            if (products.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-16">
                        <i class="fas fa-search text-6xl text-gray-300 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">No products found</h3>
                        <p class="text-gray-500">Try adjusting your search or filters</p>
                    </div>
                `;
                return;
            }

            const productsHTML = products.map(product => this.renderProductCard(product)).join('');
            container.innerHTML = `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">${productsHTML}</div>`;

            // Trigger animations
            const cards = container.querySelectorAll('.product-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '0';
                    card.style.transform = 'translateY(20px)';
                    setTimeout(() => {
                        card.style.transition = 'all 0.5s ease';
                        card.style.opacity = '1';
                        card.style.transform = 'translateY(0)';
                    }, 50);
                }, index * 50);
            });
        }

        renderProductCard(product) {
            const images = product.images ? product.images.split(',') : [];
            const mainImage = images[product.main_image_index || 0] || images[0] || 'default.jpg';
            
            return `
                <div class="product-card bg-white rounded-xl shadow-md overflow-hidden hover-scale relative">
                    <button class="add-to-compare-btn" data-product-id="${product.id}" title="Add to comparison">
                        <i class="fas fa-balance-scale"></i>
                    </button>
                    <a href="product-details.php?id=${product.id}" class="block">
                        <div class="relative">
                            <img src="uploads/images/${mainImage.trim()}" 
                                 alt="${this.escapeHtml(product.name)}" 
                                 class="w-full h-48 object-cover"
                                 loading="lazy">
                            ${product.featured ? '<span class="absolute top-2 right-2 bg-amber-500 text-white text-xs px-2 py-1 rounded-full">Featured</span>' : ''}
                            ${product.stock <= 5 && product.stock > 0 ? '<span class="absolute bottom-2 left-2 bg-red-500 text-white text-xs px-2 py-1 rounded-full">Low Stock</span>' : ''}
                            ${product.stock === 0 ? '<span class="absolute bottom-2 left-2 bg-gray-500 text-white text-xs px-2 py-1 rounded-full">Out of Stock</span>' : ''}
                        </div>
                        <div class="p-4">
                            <h3 class="text-lg font-semibold text-gray-800 mb-2 truncate">${this.escapeHtml(product.name)}</h3>
                            <p class="text-gray-600 text-sm mb-3 line-clamp-2">${this.escapeHtml(product.description || '')}</p>
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-2xl font-bold text-amber-600">$${parseFloat(product.price).toFixed(2)}</span>
                                <span class="text-sm text-gray-500">${this.escapeHtml(product.category)}</span>
                            </div>
                            ${product.stock > 0 ? `
                                <button class="w-full bg-amber-600 text-white py-2 px-4 rounded-lg hover:bg-amber-700 transition-colors add-to-cart" 
                                        data-product-id="${product.id}">
                                    Add to Cart
                                </button>
                            ` : `
                                <button class="w-full bg-gray-400 text-white py-2 px-4 rounded-lg cursor-not-allowed" disabled>
                                    Out of Stock
                                </button>
                            `}
                        </div>
                    </a>
                </div>
            `;
        }

        displayError(message) {
            const container = document.querySelector(this.resultsContainer);
            if (container) {
                container.innerHTML = `
                    <div class="text-center py-16">
                        <i class="fas fa-exclamation-triangle text-6xl text-red-400 mb-4"></i>
                        <h3 class="text-xl font-semibold text-gray-600 mb-2">Error</h3>
                        <p class="text-gray-500">${this.escapeHtml(message)}</p>
                    </div>
                `;
            }
        }

        updateResultsCount(count) {
            const countElement = document.getElementById('resultsCount');
            if (countElement) {
                countElement.textContent = `${count} product${count !== 1 ? 's' : ''} found`;
            }
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Expose to global scope
    window.AdvancedSearch = AdvancedSearch;

})();
