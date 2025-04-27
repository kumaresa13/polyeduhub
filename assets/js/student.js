document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Resource upload preview
    const fileInput = document.getElementById('resource_file');
    if (fileInput) {
        fileInput.addEventListener('change', function(event) {
            const fileName = event.target.files[0].name;
            const nextSibling = event.target.nextElementSibling;
            nextSibling.innerHTML = fileName;
        });
    }

    // Optional: Form validation
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});