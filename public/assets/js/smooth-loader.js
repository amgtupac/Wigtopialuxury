/**
 * Smooth Loading and Scrolling Animations
 * Handles page transitions, scroll animations, and smooth loading effects
 */

(function() {
    'use strict';

    // Page Load Handler
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize all smooth animations
        initSmoothScrolling();
        initScrollAnimations();
        initImageLoading();
        initPageTransitions();
        initLazyLoading();
        
        // Fade in body
        document.body.style.opacity = '1';
    });

    /**
     * Initialize smooth scrolling for anchor links
     */
    function initSmoothScrolling() {
        // Handle all anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                
                // Skip if it's just "#"
                if (href === '#' || href === '#!') {
                    return;
                }
                
                const target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Smooth scroll to top button (if exists)
        const scrollToTopBtn = document.getElementById('scrollToTop');
        if (scrollToTopBtn) {
            scrollToTopBtn.addEventListener('click', function() {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });

            // Show/hide scroll to top button
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    scrollToTopBtn.style.opacity = '1';
                    scrollToTopBtn.style.pointerEvents = 'all';
                } else {
                    scrollToTopBtn.style.opacity = '0';
                    scrollToTopBtn.style.pointerEvents = 'none';
                }
            });
        }
    }

    /**
     * Initialize scroll-triggered animations
     */
    function initScrollAnimations() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    // Optionally unobserve after animation
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Observe all elements with animation classes
        const animatedElements = document.querySelectorAll(
            '.fade-in-scroll, .slide-in-left, .slide-in-right, .scale-in'
        );
        
        animatedElements.forEach(el => observer.observe(el));
    }

    /**
     * Initialize smooth image loading
     */
    function initImageLoading() {
        const images = document.querySelectorAll('img');
        
        images.forEach(img => {
            // If image is already loaded
            if (img.complete) {
                img.classList.add('loaded');
            } else {
                // Add loaded class when image loads
                img.addEventListener('load', function() {
                    this.classList.add('loaded');
                });
                
                // Handle error case
                img.addEventListener('error', function() {
                    this.classList.add('loaded');
                });
            }
        });
    }

    /**
     * Initialize page transitions for internal links
     */
    function initPageTransitions() {
        // Create transition overlay if it doesn't exist
        if (!document.querySelector('.page-transition-overlay')) {
            const overlay = document.createElement('div');
            overlay.className = 'page-transition-overlay';
            overlay.innerHTML = '<div class="spinner"></div>';
            document.body.appendChild(overlay);
        }

        // Handle internal navigation links
        const internalLinks = document.querySelectorAll('a[href^="/"]:not([target="_blank"]), a[href^="."]:not([target="_blank"])');
        
        internalLinks.forEach(link => {
            // Skip if link has no-transition class or is a hash link
            if (link.classList.contains('no-transition') || link.getAttribute('href').startsWith('#')) {
                return;
            }

            link.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                
                // Skip external links and special cases
                if (!href || href === '#' || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
                    return;
                }

                // Only apply transition for same-origin links
                try {
                    const url = new URL(href, window.location.origin);
                    if (url.origin === window.location.origin) {
                        e.preventDefault();
                        
                        // Show transition overlay
                        const overlay = document.querySelector('.page-transition-overlay');
                        overlay.classList.add('active');
                        
                        // Navigate after a short delay
                        setTimeout(function() {
                            window.location.href = href;
                        }, 300);
                    }
                } catch (err) {
                    // If URL parsing fails, just navigate normally
                    console.log('Navigation error:', err);
                }
            });
        });
    }

    /**
     * Initialize lazy loading for images and content
     */
    function initLazyLoading() {
        if ('IntersectionObserver' in window) {
            const lazyImageObserver = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const lazyImage = entry.target;
                        
                        // Load image
                        if (lazyImage.dataset.src) {
                            lazyImage.src = lazyImage.dataset.src;
                            lazyImage.classList.add('loaded');
                        }
                        
                        // Load background image
                        if (lazyImage.dataset.bg) {
                            lazyImage.style.backgroundImage = `url(${lazyImage.dataset.bg})`;
                        }
                        
                        lazyImage.classList.remove('lazy');
                        lazyImageObserver.unobserve(lazyImage);
                    }
                });
            });

            // Observe all lazy elements
            const lazyImages = document.querySelectorAll('.lazy, [data-src], [data-bg]');
            lazyImages.forEach(img => lazyImageObserver.observe(img));
        }
    }

    /**
     * Add smooth loading state to forms
     */
    function initFormLoading() {
        const forms = document.querySelectorAll('form');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = this.querySelector('button[type="submit"], input[type="submit"]');
                
                if (submitBtn && !submitBtn.classList.contains('no-loading')) {
                    // Add loading state
                    submitBtn.disabled = true;
                    submitBtn.classList.add('loading');
                    
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<span class="loading-spinner"></span> Loading...';
                    
                    // Store original text for potential restoration
                    submitBtn.dataset.originalText = originalText;
                }
            });
        });
    }

    // Initialize form loading
    initFormLoading();

    /**
     * Smooth scroll reveal for elements
     */
    function revealOnScroll() {
        const reveals = document.querySelectorAll('.reveal');
        
        reveals.forEach(element => {
            const windowHeight = window.innerHeight;
            const elementTop = element.getBoundingClientRect().top;
            const elementVisible = 150;
            
            if (elementTop < windowHeight - elementVisible) {
                element.classList.add('active');
            }
        });
    }

    window.addEventListener('scroll', revealOnScroll);

    /**
     * Add smooth hover effects to cards
     */
    function initCardHoverEffects() {
        const cards = document.querySelectorAll('.product-card, .category-card, .stat-card, .card-hover');
        
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
                this.style.transition = 'all 0.3s ease';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
    }

    // Initialize card effects
    initCardHoverEffects();

    /**
     * Smooth loading indicator for AJAX requests
     */
    window.showLoadingIndicator = function() {
        const overlay = document.querySelector('.page-transition-overlay');
        if (overlay) {
            overlay.classList.add('active');
        }
    };

    window.hideLoadingIndicator = function() {
        const overlay = document.querySelector('.page-transition-overlay');
        if (overlay) {
            overlay.classList.remove('active');
        }
    };

    /**
     * Add entrance animations to elements
     */
    function addEntranceAnimations() {
        const elements = document.querySelectorAll('.animate-on-load');
        
        elements.forEach((element, index) => {
            setTimeout(() => {
                element.classList.add('visible');
            }, index * 100);
        });
    }

    // Run entrance animations
    addEntranceAnimations();

    /**
     * Parallax scrolling effect (optional)
     */
    function initParallax() {
        const parallaxElements = document.querySelectorAll('.parallax');
        
        if (parallaxElements.length > 0) {
            window.addEventListener('scroll', function() {
                const scrolled = window.pageYOffset;
                
                parallaxElements.forEach(element => {
                    const speed = element.dataset.speed || 0.5;
                    element.style.transform = `translateY(${scrolled * speed}px)`;
                });
            });
        }
    }

    initParallax();

    // Export functions for global use
    window.smoothLoader = {
        showLoading: window.showLoadingIndicator,
        hideLoading: window.hideLoadingIndicator,
        revealOnScroll: revealOnScroll,
        initScrollAnimations: initScrollAnimations
    };

})();
