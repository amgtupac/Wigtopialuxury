/**
 * Loading Skeleton Manager
 * Handles showing/hiding loading skeletons for better UX
 */

(function() {
    'use strict';

    const SkeletonLoader = {
        /**
         * Show product grid skeleton
         */
        showProductSkeleton: function(container, count = 8) {
            const skeletonHTML = `
                <div class="skeleton-grid" id="productSkeleton">
                    ${Array(count).fill(0).map(() => `
                        <div class="skeleton-product-card">
                            <div class="skeleton skeleton-image"></div>
                            <div class="skeleton skeleton-title"></div>
                            <div class="skeleton skeleton-text"></div>
                            <div class="skeleton skeleton-text-short"></div>
                            <div class="skeleton skeleton-price"></div>
                            <div class="skeleton skeleton-button"></div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            if (typeof container === 'string') {
                container = document.querySelector(container);
            }
            
            if (container) {
                container.innerHTML = skeletonHTML;
            }
        },

        /**
         * Show table skeleton
         */
        showTableSkeleton: function(container, rows = 5) {
            const skeletonHTML = `
                <div id="tableSkeleton">
                    ${Array(rows).fill(0).map(() => `
                        <div class="skeleton-table-row">
                            <div class="skeleton skeleton-table-cell"></div>
                            <div class="skeleton skeleton-table-cell"></div>
                            <div class="skeleton skeleton-table-cell"></div>
                            <div class="skeleton skeleton-table-cell"></div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            if (typeof container === 'string') {
                container = document.querySelector(container);
            }
            
            if (container) {
                container.innerHTML = skeletonHTML;
            }
        },

        /**
         * Show dashboard cards skeleton
         */
        showDashboardSkeleton: function(container, count = 4) {
            const skeletonHTML = `
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4" id="dashboardSkeleton">
                    ${Array(count).fill(0).map(() => `
                        <div class="skeleton-dashboard-card">
                            <div class="skeleton skeleton-stat-value"></div>
                            <div class="skeleton skeleton-stat-label"></div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            if (typeof container === 'string') {
                container = document.querySelector(container);
            }
            
            if (container) {
                container.innerHTML = skeletonHTML;
            }
        },

        /**
         * Show order items skeleton
         */
        showOrderSkeleton: function(container, count = 3) {
            const skeletonHTML = `
                <div id="orderSkeleton">
                    ${Array(count).fill(0).map(() => `
                        <div class="skeleton-order-item">
                            <div class="skeleton skeleton-order-image"></div>
                            <div class="skeleton-order-details">
                                <div class="skeleton skeleton-title"></div>
                                <div class="skeleton skeleton-text"></div>
                                <div class="skeleton skeleton-text-short"></div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            if (typeof container === 'string') {
                container = document.querySelector(container);
            }
            
            if (container) {
                container.innerHTML = skeletonHTML;
            }
        },

        /**
         * Show category cards skeleton
         */
        showCategorySkeleton: function(container, count = 4) {
            const skeletonHTML = `
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4" id="categorySkeleton">
                    ${Array(count).fill(0).map(() => `
                        <div class="skeleton-category-card">
                            <div class="skeleton skeleton-category-icon"></div>
                            <div class="skeleton skeleton-category-name"></div>
                        </div>
                    `).join('')}
                </div>
            `;
            
            if (typeof container === 'string') {
                container = document.querySelector(container);
            }
            
            if (container) {
                container.innerHTML = skeletonHTML;
            }
        },

        /**
         * Remove skeleton and show content
         */
        hideSkeleton: function(skeletonId) {
            const skeleton = document.getElementById(skeletonId);
            if (skeleton) {
                skeleton.remove();
            }
        },

        /**
         * Wrap content with skeleton loader
         */
        wrapWithSkeleton: function(element, skeletonType = 'pulse') {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }
            
            if (element && !element.classList.contains('skeleton-wrapper')) {
                element.classList.add('skeleton-wrapper');
                
                // Add skeleton class to immediate children
                Array.from(element.children).forEach(child => {
                    child.classList.add('loading-content');
                });
                
                // Create skeleton based on type
                const skeleton = document.createElement('div');
                skeleton.className = `skeleton skeleton-${skeletonType}`;
                skeleton.style.width = '100%';
                skeleton.style.height = element.offsetHeight + 'px';
                
                element.insertBefore(skeleton, element.firstChild);
            }
        },

        /**
         * Mark content as loaded
         */
        markAsLoaded: function(element) {
            if (typeof element === 'string') {
                element = document.querySelector(element);
            }
            
            if (element) {
                element.classList.add('loaded');
            }
        },

        /**
         * Auto-detect and apply skeletons to images
         */
        initImageSkeletons: function() {
            const images = document.querySelectorAll('img[data-skeleton]');
            
            images.forEach(img => {
                if (!img.complete) {
                    const wrapper = document.createElement('div');
                    wrapper.className = 'skeleton-wrapper';
                    wrapper.style.width = img.width || '100%';
                    wrapper.style.height = img.height || '200px';
                    
                    const skeleton = document.createElement('div');
                    skeleton.className = 'skeleton skeleton-image';
                    skeleton.style.width = '100%';
                    skeleton.style.height = '100%';
                    
                    img.parentNode.insertBefore(wrapper, img);
                    wrapper.appendChild(skeleton);
                    wrapper.appendChild(img);
                    
                    img.classList.add('loading-content');
                    
                    img.addEventListener('load', function() {
                        wrapper.classList.add('loaded');
                    });
                    
                    img.addEventListener('error', function() {
                        wrapper.classList.add('loaded');
                    });
                }
            });
        },

        /**
         * Show skeleton for AJAX content
         */
        showAjaxSkeleton: function(container, type = 'product', count = 4) {
            const skeletonTypes = {
                'product': this.showProductSkeleton,
                'table': this.showTableSkeleton,
                'dashboard': this.showDashboardSkeleton,
                'order': this.showOrderSkeleton,
                'category': this.showCategorySkeleton
            };
            
            const showFunction = skeletonTypes[type];
            if (showFunction) {
                showFunction.call(this, container, count);
            }
        },

        /**
         * Replace skeleton with actual content
         */
        replaceWithContent: function(skeletonId, content) {
            const skeleton = document.getElementById(skeletonId);
            if (skeleton) {
                if (typeof content === 'string') {
                    skeleton.outerHTML = content;
                } else {
                    skeleton.parentNode.replaceChild(content, skeleton);
                }
            }
        }
    };

    // Auto-initialize image skeletons on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            SkeletonLoader.initImageSkeletons();
        });
    } else {
        SkeletonLoader.initImageSkeletons();
    }

    // Expose to global scope
    window.SkeletonLoader = SkeletonLoader;

})();
