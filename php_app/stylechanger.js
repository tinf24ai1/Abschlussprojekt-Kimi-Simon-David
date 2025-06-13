const styleSwitcher = document.getElementById('style-switcher');
const stylesheet = document.getElementById('stylesheet');

styleSwitcher.addEventListener('change', function() {
    stylesheet.setAttribute('href', this.value);
});
