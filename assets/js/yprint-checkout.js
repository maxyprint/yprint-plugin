/**
 * YPrint Checkout JavaScript
 * 
 * Handles the checkout process functionality
 */
jQuery(document).ready(function($) {
    
    // Cache DOM elements
    const $container = $('.yprint-checkout-container');
    const $steps = $('.yprint-checkout-step');
    const $progressSteps = $('.yprint-progress-step');
    
    // Set initial state
    $container.attr('data-active-step', '1');
    
    /**
     * Navigate to a specific step
     * @param {number} stepNumber - The step number to navigate to
     */
    function navigateToStep(stepNumber) {
        // Hide all steps and show the active one
        $steps.removeClass('active');
        $(`#yprint-step-${getStepName(stepNumber)}`).addClass('active');
        
        // Update progress indicators
        $progressSteps.removeClass('active completed');
        
        // Mark current step as active and previous steps as completed
        $progressSteps.each(function() {
            const $step = $(this);
            const step = parseInt($step.data('step'));
            
            if (step === stepNumber) {
                $step.addClass('active');
            } else if (step < stepNumber) {
                $step.addClass('completed');
            }
        });
        
        // Update progress bar
        $container.attr('data-active-step', stepNumber);
        
        // Scroll to top of step
        $('html, body').animate({
            scrollTop: $container.offset().top - 50
        }, 300);
    }
    
    /**
     * Get step name from step number
     * @param {number} stepNumber - The step number
     * @return {string} The step name
     */
    function getStepName(stepNumber) {
        const stepNames = {
            1: 'address',
            2: 'payment',
            3: 'confirmation'
        };
        
        return stepNames[stepNumber] || 'address';
    }
    
    // Handle "Continue" button clicks
    $('.yprint-continue-button').on('click', function() {
        const nextStep = parseInt($(this).data('next-step'));
        navigateToStep(nextStep);
    });
    
    // Handle "Back" button clicks
    $('.yprint-back-button').on('click', function() {
        const prevStep = parseInt($(this).data('prev-step'));
        navigateToStep(prevStep);
    });
    
    // Handle progress step indicator clicks (for direct navigation)
    $('.yprint-progress-step').on('click', function() {
        const $currentActive = $('.yprint-progress-step.active');
        const currentStep = parseInt($currentActive.data('step'));
        const clickedStep = parseInt($(this).data('step'));
        
        // Only allow navigation to completed steps or the next available step
        if (clickedStep < currentStep || clickedStep === currentStep + 1) {
            navigateToStep(clickedStep);
        }
    });
    
    // Placeholder for "Place Order" button functionality
    // Will be implemented in later steps
    $('#yprint-place-order').on('click', function() {
        alert('Bestellung wird in einem späteren Schritt implementiert.');
    });
    
    // Initialize the page
    function initCheckout() {
        // Set initial step
        navigateToStep(1);
        
        // Log initialization (for testing)
        console.log('YPrint Checkout initialized');
    }
    
    // Run initialization
    initCheckout();
});