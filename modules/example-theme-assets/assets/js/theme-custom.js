/**
 * Example Theme Custom Scripts
 * Loaded via theme.footer hook
 */

(function() {
    'use strict';

    console.log('Theme custom scripts initialized');

    // Example 1: Smooth scroll for anchor links
    function initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(function(anchor) {
            anchor.addEventListener('click', function(e) {
                var href = this.getAttribute('href');
                if (href === '#') return;

                var target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    // Example 2: Lazy load images
    function initLazyLoad() {
        var images = document.querySelectorAll('img[data-lazy-src]');

        if ('IntersectionObserver' in window) {
            var imageObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        img.src = img.getAttribute('data-lazy-src');
                        img.removeAttribute('data-lazy-src');
                        imageObserver.unobserve(img);
                    }
                });
            });

            images.forEach(function(img) {
                imageObserver.observe(img);
            });
        } else {
            // Fallback for older browsers
            images.forEach(function(img) {
                img.src = img.getAttribute('data-lazy-src');
                img.removeAttribute('data-lazy-src');
            });
        }
    }

    // Example 3: Add "read more" functionality
    function initReadMore() {
        document.querySelectorAll('[data-read-more]').forEach(function(element) {
            var maxHeight = parseInt(element.getAttribute('data-read-more')) || 200;

            if (element.scrollHeight > maxHeight) {
                element.style.maxHeight = maxHeight + 'px';
                element.style.overflow = 'hidden';
                element.style.position = 'relative';

                var button = document.createElement('button');
                button.textContent = 'Read more';
                button.className = 'btn btn-sm btn-theme-custom mt-2';
                button.addEventListener('click', function() {
                    if (element.style.maxHeight === maxHeight + 'px') {
                        element.style.maxHeight = element.scrollHeight + 'px';
                        button.textContent = 'Read less';
                    } else {
                        element.style.maxHeight = maxHeight + 'px';
                        button.textContent = 'Read more';
                    }
                });

                element.parentNode.insertBefore(button, element.nextSibling);
            }
        });
    }

    // Example 4: Back to top button
    function initBackToTop() {
        var button = document.createElement('button');
        button.innerHTML = '↑';
        button.className = 'back-to-top';
        button.style.cssText = 'position:fixed;bottom:20px;right:20px;display:none;z-index:1000;' +
                               'width:50px;height:50px;border-radius:50%;border:none;' +
                               'background:#667eea;color:white;font-size:24px;cursor:pointer;' +
                               'box-shadow:0 2px 10px rgba(0,0,0,0.2);transition:all 0.3s ease;';

        button.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        document.body.appendChild(button);

        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                button.style.display = 'block';
            } else {
                button.style.display = 'none';
            }
        });
    }

    // Initialize all features when DOM is ready
    function init() {
        initSmoothScroll();
        initLazyLoad();
        initReadMore();
        initBackToTop();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Export utilities globally
    window.ThemeUtils = {
        smoothScrollTo: function(selector) {
            var target = document.querySelector(selector);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    };

})();
