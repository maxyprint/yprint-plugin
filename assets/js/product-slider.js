/**
 * YPrint Product Slider JavaScript
 */
(function($) {
    'use strict';

    // Enhanced slider functionality
    window.YPrintProductSlider = {
        init: function() {
            this.bindEvents();
            this.handleResize();
        },

        bindEvents: function() {
            // Auto-initialize all sliders on page load
            $(document).ready(function() {
                $('.yprint-product-slider-container').each(function() {
                    YPrintProductSlider.initializeSlider($(this));
                });
            });

            // Handle dynamic content
            $(document).on('slider:refresh', function() {
                $('.yprint-product-slider-container').each(function() {
                    YPrintProductSlider.initializeSlider($(this));
                });
            });
        },

        initializeSlider: function($container) {
            if ($container.data('slider-initialized')) {
                return;
            }

            const $track = $container.find('.yprint-product-slider-track');
            const $prevBtn = $container.find('.yprint-slider-prev');
            const $nextBtn = $container.find('.yprint-slider-next');
            const $cards = $container.find('.yprint-product-card');

            if (!$track.length || !$cards.length) return;

            let currentIndex = 0;
            const cardWidth = 300;
            const visibleCards = Math.floor($container.width() / cardWidth);
            const maxIndex = Math.max(0, $cards.length - visibleCards);

            // Update slider position
            function updateSlider() {
                const translateX = -(currentIndex * cardWidth);
                $track.css('transform', `translateX(${translateX}px)`);
                
                $prevBtn.prop('disabled', currentIndex <= 0);
                $nextBtn.prop('disabled', currentIndex >= maxIndex);
            }

            // Navigation events
            $prevBtn.on('click', function() {
                if (currentIndex > 0) {
                    currentIndex = Math.max(0, currentIndex - visibleCards);
                    updateSlider();
                }
            });

            $nextBtn.on('click', function() {
                if (currentIndex < maxIndex) {
                    currentIndex = Math.min(maxIndex, currentIndex + visibleCards);
                    updateSlider();
                }
            });

            // Keyboard navigation
            $container.on('keydown', function(e) {
                if (e.key === 'ArrowLeft') {
                    $prevBtn.click();
                } else if (e.key === 'ArrowRight') {
                    $nextBtn.click();
                }
            });

            // Initialize
            updateSlider();
            $container.data('slider-initialized', true);

            // Auto-scroll functionality (optional)
            if ($container.data('auto-scroll')) {
                setInterval(function() {
                    if (currentIndex >= maxIndex) {
                        currentIndex = 0;
                    } else {
                        currentIndex++;
                    }
                    updateSlider();
                }, 5000);
            }
        },

        handleResize: function() {
            let resizeTimeout;
            $(window).on('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    $('.yprint-product-slider-container').removeData('slider-initialized');
                    YPrintProductSlider.init();
                }, 250);
            });
        }
    };

    // Initialize on document ready
    YPrintProductSlider.init();

})(jQuery);