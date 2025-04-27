document.addEventListener('DOMContentLoaded', function() {
    // Get all alert elements
    const alerts = document.querySelectorAll('.alert-dismissible');
    
    // Add automatic dismissal after 5 seconds
    alerts.forEach(function(alert) {
        setTimeout(function() {
            // Add fade-out animation
            alert.style.opacity = '1';
            
            // Create fade-out effect
            let opacity = 1;
            const timer = setInterval(function() {
                if (opacity <= 0.1) {
                    clearInterval(timer);
                    alert.style.display = 'none';
                }
                alert.style.opacity = opacity;
                opacity -= opacity * 0.1;
            }, 50);
            
        }, 5000); // 5 seconds
    });
    
    // Add click handlers to close buttons
    const closeButtons = document.querySelectorAll('.alert .close');
    closeButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            const alert = this.closest('.alert');
            alert.style.display = 'none';
        });
    });
});