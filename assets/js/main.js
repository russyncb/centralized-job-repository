// Main JavaScript file for ShaSha CJRS

// Wait for the DOM to be loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('ShaSha CJRS initialized');
    
    // Auto-hide messages after 5 seconds
    const messages = document.querySelectorAll('.success-message, .error-message');
    messages.forEach(function(message) {
        setTimeout(function() {
            message.style.opacity = '0';
            setTimeout(function() {
                message.style.display = 'none';
            }, 500);
        }, 5000);
    });
    
    // Form validation
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(event) {
            const requiredFields = form.querySelectorAll('[required]');
            let valid = true;
            
            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    valid = false;
                    field.classList.add('error');
                } else {
                    field.classList.remove('error');
                }
            });
            
            if (!valid) {
                event.preventDefault();
                alert('Please fill in all required fields.');
            }
        });
    });
});