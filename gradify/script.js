// script.js - Desktop only functionality
document.addEventListener('DOMContentLoaded', function() {
    
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredInputs = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredInputs.forEach(input => {
                if (!input.value.trim()) {
                    isValid = false;
                    input.style.borderColor = '#f56565';
                    
                    let errorMsg = input.nextElementSibling;
                    if (!errorMsg || !errorMsg.classList.contains('error-msg')) {
                        errorMsg = document.createElement('div');
                        errorMsg.className = 'error-msg';
                        errorMsg.style.color = '#f56565';
                        errorMsg.style.fontSize = '0.875rem';
                        errorMsg.style.marginTop = '0.25rem';
                        errorMsg.textContent = 'This field is required';
                        input.parentNode.appendChild(errorMsg);
                    }
                } else {
                    input.style.borderColor = '';
                    const errorMsg = input.nextElementSibling;
                    if (errorMsg && errorMsg.classList.contains('error-msg')) {
                        errorMsg.remove();
                    }
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                return false;
            }
            return true;
        });
    });
    
    // Time validation for session forms
    const sessionForms = document.querySelectorAll('form[action*="sessions.php"]');
    sessionForms.forEach(form => {
        const startTime = form.querySelector('[name="start_time"]');
        const endTime = form.querySelector('[name="end_time"]');
        const dateInput = form.querySelector('[name="session_date"]');
        
        if (startTime && endTime && dateInput) {
            // Set minimum date to today
            const today = new Date().toISOString().split('T')[0];
            dateInput.min = today;
            
            // Validate time on change
            [startTime, endTime].forEach(input => {
                input.addEventListener('change', validateTime);
            });
            
            function validateTime() {
                if (startTime.value && endTime.value) {
                    if (startTime.value >= endTime.value) {
                        endTime.style.borderColor = '#f56565';
                        endTime.setCustomValidity('End time must be after start time');
                    } else {
                        endTime.style.borderColor = '';
                        endTime.setCustomValidity('');
                    }
                }
            }
        }
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s';
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 500);
        }, 5000);
    });
    
    // Initialize date inputs
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        if (!input.value && input.name === 'session_date') {
            input.value = new Date().toISOString().split('T')[0];
        }
    });
    
    // Initialize time inputs
    const timeInputs = document.querySelectorAll('input[type="time"]');
    timeInputs.forEach(input => {
        if (!input.value && input.name === 'start_time') {
            input.value = '09:00';
        }
        if (!input.value && input.name === 'end_time') {
            input.value = '10:00';
        }
    });
    
    // Confirmation for delete buttons
    const deleteButtons = document.querySelectorAll('.btn-danger, .btn-delete');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this item?')) {
                e.preventDefault();
                return false;
            }
        });
    });
});