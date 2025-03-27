document.addEventListener('DOMContentLoaded', function() {
    // Theme switcher
    const themeRadios = document.querySelectorAll('input[name="theme"]');
    
    // Load saved theme or default to pure dark
    const savedTheme = localStorage.getItem('selectedTheme') || 'default';
    
    // Set the saved theme
    themeRadios.forEach(radio => {
        if (radio.value === savedTheme) {
            radio.checked = true;
        }
        
        radio.addEventListener('change', function() {
            const theme = this.value;
            // Remove all theme classes first
            document.body.classList.remove(
                'dark-blue-theme', 
                'dark-green-theme', 
                'light-theme'
            );
            
            if (theme !== 'default') {
                document.body.classList.add(`${theme}-theme`);
            }
            
            // Save to localStorage
            localStorage.setItem('selectedTheme', theme);
        });
    });
    
    // Apply saved theme on load
    if (savedTheme !== 'default') {
        document.body.classList.add(`${savedTheme}-theme`);
    }
});
