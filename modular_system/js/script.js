function confirmDelete() {
    return confirm("Are you sure you want to delete this item?");
}

// Basic client-side validation can be added here if needed,
// but always rely on server-side validation as the primary security measure.

document.addEventListener('DOMContentLoaded', function() {
    // Example: Auto-focus on the first form field (if one exists)
    const firstForm = document.querySelector('form');
    if (firstForm) {
        const firstInput = firstForm.querySelector('input:not([type="hidden"]), textarea, select');
        if (firstInput) {
            // firstInput.focus(); // Uncomment if you want auto-focus
        }
    }

    // Example: Smoothly fade out flash messages after a few seconds
    const flashMessages = document.querySelectorAll('.flash-message');
    flashMessages.forEach(function(flashMessage) {
        setTimeout(function() {
            flashMessage.style.transition = 'opacity 0.5s ease';
            flashMessage.style.opacity = '0';
            setTimeout(function() {
                flashMessage.remove();
            }, 500); // Remove from DOM after fade out
        }, 5000); // 5 seconds
    });
});
