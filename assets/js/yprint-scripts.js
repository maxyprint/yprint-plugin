/**
 * YPrint Plugin Scripts
 */

(function($) {
    'use strict';

    // Registration form validation and submission
    $(document).ready(function() {
        // Handle registration form submission
        $('#register-form').on('submit', function(e) {
            e.preventDefault();
            
            // Reset messages
            $('#registration-message').removeClass('success error').hide();
            
            // Get form data
            const username = $('#user_login').val();
            const email = $('#user_email').val();
            const password = $('#user_password').val();
            const confirmPassword = $('#user_password_confirm').val();
            
            // Basic validation
            if (!username || !email || !password || !confirmPassword) {
                showMessage('All fields are required.', 'error');
                return;
            }
            
            // Validate email format
            if (!isValidEmail(email)) {
                showMessage('Please enter a valid email address.', 'error');
                return;
            }
            
            // Password validation
            if (!isValidPassword(password)) {
                showMessage('Password must be at least 8 characters long, include a capital letter, and a special character.', 'error');
                return;
            }
            
            // Confirm passwords match
            if (password !== confirmPassword) {
                showMessage('Passwords do not match.', 'error');
                return;
            }
            
            // Send AJAX request
            $.ajax({
                type: 'POST',
                url: yprint_ajax.ajax_url,
                data: {
                    action: 'yprint_register_user',
                    username: username,
                    email: email,
                    password: password,
                    nonce: yprint_ajax.nonce
                },
                beforeSend: function() {
                    // Display loading state
                    $('input[type="submit"]').prop('disabled', true).val('Processing...');
                },
                success: function(response) {
                    if (response.success) {
                        showMessage(response.data.message || 'Registration successful! Please check your email to verify your account.', 'success');
                        $('#register-form')[0].reset();
                    } else {
                        showMessage(response.data.message || 'Registration failed. Please try again.', 'error');
                    }
                },
                error: function() {
                    showMessage('Server error. Please try again later.', 'error');
                },
                complete: function() {
                    // Reset button state
                    $('input[type="submit"]').prop('disabled', false).val('Register');
                }
            });
        });
        
        // Real-time email validation
        $('#user_email').on('blur', function() {
            const email = $(this).val();
            if (email && !isValidEmail(email)) {
                $('#email-validity').css('color', '#dc3545').text('Please enter a valid email address.');
            } else {
                $('#email-validity').css('color', '#6c757d').text('Email must be from a leading provider (e.g., gmail.com, yahoo.com).');
            }
        });
        
        // Real-time password validation
        $('#user_password').on('input', function() {
            const password = $(this).val();
            if (password) {
                if (isValidPassword(password)) {
                    $('#password-hint').css('color', '#28a745').text('Password meets requirements.');
                } else {
                    $('#password-hint').css('color', '#dc3545').text('Password must be at least 8 characters long, include a capital letter, and a special character.');
                }
            } else {
                $('#password-hint').css('color', '#6c757d').text('Password must be at least 8 characters long, include a capital letter, and a special character.');
            }
        });
        
        // Real-time confirm password validation
        $('#user_password_confirm').on('input', function() {
            const confirmPassword = $(this).val();
            const password = $('#user_password').val();
            
            if (confirmPassword) {
                if (confirmPassword === password) {
                    $('#confirm-password-hint').css('color', '#28a745').text('Passwords match.');
                } else {
                    $('#confirm-password-hint').css('color', '#dc3545').text('Passwords do not match.');
                }
            } else {
                $('#confirm-password-hint').css('color', '#6c757d').text('Passwords must match.');
            }
        });
    });
    
    /**
     * Check if email is valid
     * @param {string} email - The email to validate
     * @return {boolean} True if valid, false otherwise
     */
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    /**
     * Check if password meets requirements
     * @param {string} password - The password to validate
     * @return {boolean} True if valid, false otherwise
     */
    function isValidPassword(password) {
        // At least 8 characters, 1 uppercase letter, 1 special character
        const passwordRegex = /^(?=.*[A-Z])(?=.*[!@#$%^&*])(.{8,})$/;
        return passwordRegex.test(password);
    }
    
    /**
     * Show a message to the user
     * @param {string} message - The message to display
     * @param {string} type - The message type (success or error)
     */
    function showMessage(message, type) {
        const $messageElement = $('#registration-message');
        $messageElement.removeClass('success error').addClass(type).html(message).fadeIn();
        
        // Scroll to message
        $('html, body').animate({
            scrollTop: $messageElement.offset().top - 100
        }, 500);
    }
    
})(jQuery);