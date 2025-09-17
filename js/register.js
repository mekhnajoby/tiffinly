$(document).ready(function () {
    // Initialize error icons
    $('#registrationForm')[0].reset();
    const initErrorIcon = (fieldId) => {
        $(`#${fieldId}`).on('input change', function() {
            if ($(this).val().trim() !== '') {
                $(`#${fieldId}-error-icon`).hide();
                $(this).removeClass('is-invalid');
                $(`#${fieldId}-error`).text('');
            }
        });
    };

    // Initialize all error icons
    const fields = ['name', 'email', 'phone', 'password', 'confirm_password', 
                   'vehicle_type', 'vehicle_number', 'license_number', 'license_file', 'availability', 'aadhar_number'];
    fields.forEach(field => initErrorIcon(field));

    // Show/hide delivery fields based on role selection
    $('input[name="userRole"]').on('change', function () {
        const isDelivery = $('#delivery').is(':checked');
        $('#deliveryFields').toggleClass('d-none', !isDelivery);
        
        // Clear delivery field errors when hiding
        if (!isDelivery) {
            fields.slice(5).forEach(field => {
                $(`#${field}`).removeClass('is-invalid');
                $(`#${field}-error`).text('');
                $(`#${field}-error-icon`).hide();
            });
        }
    });

    // Show delivery fields if delivery is selected on page load
    if ($('#delivery').is(':checked')) {
        $('#deliveryFields').removeClass('d-none');
    }

    // Enhanced email validation with AJAX uniqueness check
    $('#email').on('blur', function() {
        const email = $(this).val().trim();
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (email === '') {
            showError('email', 'Email is required.');
            return;
        }
        
        if (!emailRegex.test(email)) {
            showError('email', 'Please enter a valid email address.');
            return;
        }
        
        // AJAX email uniqueness check
        $.ajax({
            url: 'register.php',
            method: 'POST',
            dataType: 'json',
            data: { 
                check_email: 1,  // Flag to indicate this is an email check
                email: email     // The actual email to check
            },
            success: function(response) {
                if (!response.valid) {
                    showError('email', 'Please enter a valid email address.');
                } else if (response.exists) {
                    showError('email', 'This email is already registered.');
                } else {
                    clearError('email');
                }
            },
            error: function() {
                showError('email', 'Error checking email availability.');
            }
        });
    });

    // Simplified form submission
    $('#registrationForm').on('submit', function(e) {
        e.preventDefault();
        submitRegistrationForm();
    });

    // New helper function for form submission
    function submitRegistrationForm() {
        let isValid = true;
        
        // Clear previous errors
        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').text('');
        $('.error-icon').hide();

        // Validate basic fields
        const name = $('#name').val().trim();
        const email = $('#email').val().trim();
        const phone = $('#phone').val().trim().replace(/\D/g, '');
        const password = $('#password').val();
        const confirmPassword = $('#confirm_password').val();
        const role = $('input[name="userRole"]:checked').val();
        const termsChecked = $('#terms').is(':checked');

        // Name validation
        if (name === '') {
            showError('name', 'Full Name is required.');
            isValid = false;
        } else if (!/^[a-zA-Z\s]{2,50}$/.test(name)) {
            showError('name', 'Name must be 2-50 letters and spaces only.');
            isValid = false;
        }

        // Email validation
        if (email === '') {
            showError('email', 'Email is required.');
            isValid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showError('email', 'Please enter a valid email address.');
            isValid = false;
        }

        // Phone validation
        if (phone === '') {
            showError('phone', 'Phone number is required.');
            isValid = false;
        } else if (!/^[6-9]\d{9}$/.test(phone)) {
            showError('phone', 'Please enter a valid 10-digit Indian phone number.');
            isValid = false;
        }

        // Password validation
        if (password === '') {
            showError('password', 'Password is required.');
            isValid = false;
        } else if (password.length < 8) {
            showError('password', 'Password must be at least 8 characters.');
            isValid = false;
        } else if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9])/.test(password)) {
            showError('password', 'Include uppercase, lowercase, number, and special character.');
            isValid = false;
        }

        // Confirm password
        if (confirmPassword === '') {
            showError('confirm_password', 'Please confirm your password.');
            isValid = false;
        } else if (password !== confirmPassword) {
            showError('confirm_password', 'Passwords do not match.');
            isValid = false;
        }

        // Terms checkbox
        if (!termsChecked) {
            showError('terms', 'You must agree to the terms and conditions.');
            isValid = false;
        }

        // Delivery partner specific validations
        if (role === 'delivery') {
            const vehicleType = $('#vehicle_type').val();
            const vehicleNumber = $('#vehicle_number').val().trim();
            const licenseNumber = $('#license_number').val().trim();
            const licenseFile = $('#license_file').val();
            const availability = $('#availability').val();
            const aadharNumber = $('#aadhar_number').val().trim();

            // Vehicle type
            if (!vehicleType) {
                showError('vehicle_type', 'Please select your vehicle type.');
                isValid = false;
            }

            // Vehicle number
            if (vehicleNumber === '') {
                showError('vehicle_number', 'Vehicle number is required.');
                isValid = false;
            } else if (!/^[A-Z]{2}[0-9]{1,2}[A-Z]{1,2}[0-9]{4}$/i.test(vehicleNumber)) {
                showError('vehicle_number', 'Please enter a valid vehicle number (e.g. TN01AB1234).');
                isValid = false;
            }

            // License number
            if (licenseNumber === '') {
                showError('license_number', 'License number is required.');
                isValid = false;
            } else if (!/^[A-Z]{2}\d{2}\d{4,11}$/i.test(licenseNumber)) {
                showError('license_number', 'Please enter a valid license number (e.g. TN22YYYYYYYY).');
                isValid = false;
            }

            // License file
            if (!licenseFile) {
                showError('license_file', 'License file is required.');
                isValid = false;
            } else {
                const validExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
                const extension = licenseFile.split('.').pop().toLowerCase();
                if (!validExtensions.includes(extension)) {
                    showError('license_file', 'Only PDF, JPG, or PNG files are allowed.');
                    isValid = false;
                }
            }

            // Availability
            if (!availability) {
                showError('availability', 'Please select your availability.');
                isValid = false;
            }

            // Aadhar number
            // Aadhar number
if (aadharNumber === '') {
    showError('aadhar_number', 'Aadhar number is required.');
    isValid = false;
} else if (!/^\d{12}$/.test(aadharNumber)) {  // Changed from 10 to 12
    showError('aadhar_number', 'Aadhar number must be 12 digits.');
    isValid = false;
}

        }

        if (isValid) {
            // Show loading indicator
            $('.register-btn').html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...').prop('disabled', true);
            
            // Submit form via AJAX
            const formData = new FormData($('#registrationForm')[0]);
            
            $.ajax({
    url: 'register.php',
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    dataType: 'json',
    headers: {
        'X-Requested-With': 'XMLHttpRequest'  // Add this header
    },
    success: function(response) {
        $('.register-btn').html('<i class="fas fa-user-plus me-2"></i> Create My Account').prop('disabled', false);
        
        if (response.status === 'success') {
    // Create a new container for the success message
    const successHtml = `
        <div class="auth-container">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="alert alert-success text-center mt-5">
                            <i class="fas fa-check-circle me-2"></i>
                            ${response.message}
                            <div class="spinner-border spinner-border-sm ms-2" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Replace the entire page content
    $('body').html(successHtml);
    
    // Start countdown timer
    // Start countdown timer
let seconds = 4;
const countdown = setInterval(() => {
    seconds--;
    if (seconds <= 0) {
        clearInterval(countdown);
        window.location.href = 'login.php'; // Hard-coded to login.php
    }
}, 1000);

}
 else if (response.errors) {
            // Display field-specific errors
            Object.keys(response.errors).forEach(field => {
                showError(field, response.errors[field]);
            });
        } else {
            showGeneralError(response.message || 'Registration failed. Please try again.');
        }
    },
    error: function(xhr, status, error) {
        $('.register-btn').html('<i class="fas fa-user-plus me-2"></i> Create My Account').prop('disabled', false);
        
        // Add debugging information
        console.log('AJAX Error:', {
            status: xhr.status,
            statusText: xhr.statusText,
            responseText: xhr.responseText,
            error: error
        });
        
        showGeneralError('An error occurred. Please try again.');
    }
});

        }
    }

    // Helper functions
    function showError(fieldId, message) {
        $(`#${fieldId}`).addClass('is-invalid');
        $(`#${fieldId}-error`).text(message);
        $(`#${fieldId}-error-icon`).show();
        
        // Scroll to the first error
        if ($(`#${fieldId}-error:visible`).length) {
            $('html, body').animate({
                scrollTop: $(`#${fieldId}`).offset().top - 100
            }, 500);
        }
    }

    function clearError(fieldId) {
        $(`#${fieldId}`).removeClass('is-invalid');
        $(`#${fieldId}-error`).text('');
        $(`#${fieldId}-error-icon`).hide();
    }

    function showGeneralError(message) {
        const alertHtml = `
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        $('#registrationForm').prepend(alertHtml);
    }

    function checkPasswordStrength(password) {
        let strength = 0;
        
        // Length check
        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        
        // Character type checks
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/\d/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;
        
        return strength;
    }
});