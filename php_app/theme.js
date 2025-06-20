document.addEventListener('DOMContentLoaded', () => {
    const themeSelect = document.getElementById('theme-select');
    const themeLink = document.getElementById('theme-link');
    
    // Load saved theme preference from localStorage
    const savedTheme = localStorage.getItem('selectedTheme');
    if (savedTheme) {
        themeLink.href = savedTheme;
        themeSelect.value = savedTheme;
    }

    // Handle theme changes
    themeSelect.addEventListener('change', (e) => {
        const selectedTheme = e.target.value;
        themeLink.href = selectedTheme;
        localStorage.setItem('selectedTheme', selectedTheme);
    });
});
