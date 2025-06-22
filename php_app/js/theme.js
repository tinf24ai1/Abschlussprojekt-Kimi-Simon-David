document.addEventListener('DOMContentLoaded', () => {
    const themeSelect = document.getElementById('theme-select');
    const themeLink = document.getElementById('theme-link');

    // If the theme selector or the theme link doesn't exist on this page, stop executing.
    if (!themeSelect || !themeLink) {
        return;
    }

    const currentTheme = localStorage.getItem('theme');

    // Set the dropdown to the saved theme on page load
    if (currentTheme) {
        themeLink.href = currentTheme;
        themeSelect.value = currentTheme;
    }

    // Add event listener to change theme and save choice
    themeSelect.addEventListener('change', () => {
        const selectedTheme = themeSelect.value;
        themeLink.href = selectedTheme;
        localStorage.setItem('theme', selectedTheme);
    });
});