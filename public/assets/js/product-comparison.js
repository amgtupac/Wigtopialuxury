/**
 * Product Comparison System
 * Allows users to compare multiple products side by side
 */

(function() {
    'use strict';

    class ProductComparison {
        constructor() {
            this.storageKey = 'wigtopia_comparison';
            this.maxProducts = 4;
            this.products = this.loadFromStorage();
            this.init();
        }

        init() {
            this.createComparisonBar();
            this.createComparisonModal();
            this.attachEventListeners();
            this.updateUI();
        }

        createComparisonBar() {
            if (document.getElementById('comparisonBar')) return;

            const bar = document.createElement('div');
            bar.id = 'comparisonBar';
            bar.className = 'comparison-bar';
            bar.innerHTML = `
                <div class="comparison-bar-content">
                    <div class="comparison-items" id="comparisonItems"></div>
                    <div class="comparison-actions">
                        <button class="comparison-btn comparison-btn-compare" id="compareBtn">
                            <i class="fas fa-balance-scale"></i> Compare
                        </button>
                        <button class="comparison-btn comparison-btn-clear" id="clearComparisonBtn">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(bar);
        }

        createComparisonModal() {
            if (document.getElementById('comparisonModal')) return;

            const modal = document.createElement('div');
            modal.id = 'comparisonModal';
            modal.className = 'comparison-modal';
            modal.innerHTML = `
                <div class="comparison-modal-content">
                    <div class="comparison-modal-header">
                        <h2 class="comparison-modal-title">Product Comparison</h2>
                        <button class="comparison-modal-close" id="closeComparisonModal">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="comparison-table-wrapper" id="comparisonTableWrapper">
                        <!-- Table will be inserted here -->
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        attachEventListeners() {
            // Compare button
            document.getElementById('compareBtn')?.addEventListener('click', () => {
                this.showComparison();
            });

            // Clear button
            document.getElementById('clearComparisonBtn')?.addEventListener('click', () => {
                this.clearAll();
            });

            // Close modal
            document.getElementById('closeComparisonModal')?.addEventListener('click', () => {
                this.hideComparison();
            });

            // Close modal on backdrop click
            document.getElementById('comparisonModal')?.addEventListener('click', (e) => {
                if (e.target.id === 'comparisonModal') {
                    this.hideComparison();
                }
            });

            // Delegate event for add to compare buttons
            document.addEventListener('click', (e) => {
                if (e.target.closest('.add-to-compare-btn')) {
                    const btn = e.target.closest('.add-to-compare-btn');
                    const productId = btn.dataset.productId;
                    this.toggleProduct(productId);
                }
            });
        }

        async toggleProduct(productId) {
            const index = this.products.findIndex(p => p.id == productId);
            
            if (index > -1) {
                this.removeProduct(productId);
            } else {
                if (this.products.length >= this.maxProducts) {
                    this.showNotification(`You can only compare up to ${this.maxProducts} products`, 'warning');
                    return;
                }
                await this.addProduct(productId);
            }
        }

        async addProduct(productId) {
            try {
                const response = await fetch(`get-product-data.php?id=${productId}`);
                const data = await response.json();
                
                if (data.success && data.product) {
                    this.products.push(data.product);
                    this.saveToStorage();
                    this.updateUI();
                    this.showNotification('Product added to comparison', 'success');
                }
            } catch (error) {
                console.error('Error adding product:', error);
                this.showNotification('Failed to add product', 'error');
            }
        }

        removeProduct(productId) {
            this.products = this.products.filter(p => p.id != productId);
            this.saveToStorage();
            this.updateUI();
            this.showNotification('Product removed from comparison', 'info');
        }

        clearAll() {
            if (confirm('Remove all products from comparison?')) {
                this.products = [];
                this.saveToStorage();
                this.updateUI();
                this.showNotification('Comparison cleared', 'info');
            }
        }

        updateUI() {
            this.updateComparisonBar();
            this.updateCompareButtons();
        }

        updateComparisonBar() {
            const bar = document.getElementById('comparisonBar');
            const itemsContainer = document.getElementById('comparisonItems');
            
            if (!bar || !itemsContainer) return;

            if (this.products.length > 0) {
                bar.classList.add('active');
                itemsContainer.innerHTML = this.products.map(product => this.renderComparisonItem(product)).join('');
            } else {
                bar.classList.remove('active');
                itemsContainer.innerHTML = '';
            }
        }

        renderComparisonItem(product) {
            const images = product.images ? product.images.split(',') : [];
            const mainImage = images[product.main_image_index || 0] || images[0] || 'default.jpg';
            
            return `
                <div class="comparison-item">
                    <img src="uploads/images/${mainImage.trim()}" 
                         alt="${this.escapeHtml(product.name)}" 
                         class="comparison-item-image">
                    <div class="comparison-item-info">
                        <div class="comparison-item-name">${this.escapeHtml(product.name)}</div>
                        <div class="comparison-item-price">$${parseFloat(product.price).toFixed(2)}</div>
                    </div>
                    <button class="comparison-item-remove" 
                            onclick="window.productComparison.removeProduct(${product.id})"
                            aria-label="Remove">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
        }

        updateCompareButtons() {
            document.querySelectorAll('.add-to-compare-btn').forEach(btn => {
                const productId = btn.dataset.productId;
                const isInComparison = this.products.some(p => p.id == productId);
                
                if (isInComparison) {
                    btn.classList.add('active');
                    btn.innerHTML = '<i class="fas fa-check"></i>';
                    btn.title = 'Remove from comparison';
                } else {
                    btn.classList.remove('active');
                    btn.innerHTML = '<i class="fas fa-balance-scale"></i>';
                    btn.title = 'Add to comparison';
                }
            });
        }

        showComparison() {
            if (this.products.length < 2) {
                this.showNotification('Add at least 2 products to compare', 'warning');
                return;
            }

            const modal = document.getElementById('comparisonModal');
            const tableWrapper = document.getElementById('comparisonTableWrapper');
            
            if (!modal || !tableWrapper) return;

            tableWrapper.innerHTML = this.renderComparisonTable();
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        hideComparison() {
            const modal = document.getElementById('comparisonModal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }

        renderComparisonTable() {
            const features = [
                { key: 'price', label: 'Price', format: (val) => `$${parseFloat(val).toFixed(2)}` },
                { key: 'category', label: 'Category' },
                { key: 'stock', label: 'Stock', format: (val) => this.formatStock(val) },
                { key: 'size', label: 'Size' },
                { key: 'color', label: 'Color' },
                { key: 'hair_type', label: 'Hair Type' },
                { key: 'description', label: 'Description' }
            ];

            let html = '<table class="comparison-table"><thead><tr><th>Feature</th>';
            
            // Product headers
            this.products.forEach(product => {
                const images = product.images ? product.images.split(',') : [];
                const mainImage = images[product.main_image_index || 0] || images[0] || 'default.jpg';
                
                html += `
                    <th class="comparison-product-header">
                        <img src="uploads/images/${mainImage.trim()}" 
                             alt="${this.escapeHtml(product.name)}" 
                             class="comparison-product-image">
                        <div class="comparison-product-name">${this.escapeHtml(product.name)}</div>
                        <div class="comparison-product-price">$${parseFloat(product.price).toFixed(2)}</div>
                    </th>
                `;
            });
            
            html += '</tr></thead><tbody>';

            // Feature rows
            features.forEach(feature => {
                html += `<tr><td class="comparison-feature-label">${feature.label}</td>`;
                
                this.products.forEach(product => {
                    let value = product[feature.key] || '-';
                    if (feature.format) {
                        value = feature.format(value);
                    }
                    html += `<td class="comparison-feature-value">${value}</td>`;
                });
                
                html += '</tr>';
            });

            // Add to cart buttons
            html += '<tr><td class="comparison-feature-label">Action</td>';
            this.products.forEach(product => {
                if (product.stock > 0) {
                    html += `
                        <td>
                            <button class="bg-amber-600 text-white py-2 px-4 rounded-lg hover:bg-amber-700 transition-colors w-full add-to-cart" 
                                    data-product-id="${product.id}">
                                Add to Cart
                            </button>
                        </td>
                    `;
                } else {
                    html += '<td><button class="bg-gray-400 text-white py-2 px-4 rounded-lg cursor-not-allowed w-full" disabled>Out of Stock</button></td>';
                }
            });
            html += '</tr>';

            html += '</tbody></table>';
            return html;
        }

        formatStock(stock) {
            stock = parseInt(stock);
            if (stock === 0) {
                return '<span class="comparison-stock-badge comparison-stock-out">Out of Stock</span>';
            } else if (stock <= 5) {
                return `<span class="comparison-stock-badge comparison-stock-low">Low Stock (${stock})</span>`;
            } else {
                return `<span class="comparison-stock-badge comparison-stock-in">In Stock (${stock})</span>`;
            }
        }

        saveToStorage() {
            try {
                localStorage.setItem(this.storageKey, JSON.stringify(this.products));
            } catch (e) {
                console.warn('Failed to save comparison:', e);
            }
        }

        loadFromStorage() {
            try {
                const data = localStorage.getItem(this.storageKey);
                return data ? JSON.parse(data) : [];
            } catch (e) {
                console.warn('Failed to load comparison:', e);
                return [];
            }
        }

        showNotification(message, type = 'info') {
            // Use existing notification system if available
            if (window.showNotification) {
                window.showNotification(message, type);
            } else {
                alert(message);
            }
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        getProducts() {
            return this.products;
        }

        getProductCount() {
            return this.products.length;
        }
    }

    // Initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.productComparison = new ProductComparison();
        });
    } else {
        window.productComparison = new ProductComparison();
    }

})();
