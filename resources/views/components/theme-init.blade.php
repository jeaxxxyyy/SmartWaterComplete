<script>
    (() => {
        const key = 'smart-water-theme';
        const validThemes = ['light', 'dark'];

        try {
            const savedTheme = window.localStorage?.getItem(key);
            const systemTheme = window.matchMedia?.('(prefers-color-scheme: dark)')?.matches ? 'dark' : 'light';
            const theme = validThemes.includes(savedTheme) ? savedTheme : systemTheme;
            document.documentElement.classList.toggle('dark', theme === 'dark');
            document.documentElement.dataset.theme = theme;
            document.documentElement.style.colorScheme = theme;
        } catch {
            document.documentElement.classList.remove('dark');
            document.documentElement.dataset.theme = 'light';
            document.documentElement.style.colorScheme = 'light';
        }
    })();
</script>
