/**
 * Construction Theme JavaScript
 */

(function($) {
    'use strict';
    
    // Document Ready
    $(document).ready(function() {
        
        // Mobile Menu Toggle
        $('#menu').on('click', function() {
            $('.navigation').slideToggle();
        });
        
        // Smooth Scroll
        $('a[href^="#"]').on('click', function(e) {
            var target = $(this.getAttribute('href'));
            if (target.length) {
                e.preventDefault();
                $('html, body').stop().animate({
                    scrollTop: target.offset().top - 80
                }, 1000);
            }
        });
        
        // Initialize Owl Carousel if available
        if ($.fn.owlCarousel) {
            $('.owl-carousel').owlCarousel({
                loop: true,
                margin: 30,
                nav: true,
                dots: true,
                autoplay: true,
                autoplayTimeout: 5000,
                responsive: {
                    0: { items: 1 },
                    600: { items: 2 },
                    1000: { items: 3 }
                }
            });
        }
        
        // Header Scroll Effect
        $(window).scroll(function() {
            if ($(this).scrollTop() > 100) {
                $('.header').addClass('scrolled');
            } else {
                $('.header').removeClass('scrolled');
            }
        });
        
    });
    
})(jQuery);
