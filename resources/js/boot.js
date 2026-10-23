const splashSessionKey = 'smart-water-splash-shown';
const themeKey = 'smart-water-theme';
const themes = ['light', 'dark'];

const readStoredTheme = () => {
    try {
        return window.localStorage?.getItem(themeKey);
    } catch {
        return null;
    }
};

const writeStoredTheme = (theme) => {
    try {
        window.localStorage?.setItem(themeKey, theme);
    } catch {
        // The inline theme initializer and the current page theme still work
        // when browser storage is unavailable.
    }
};

const applyTheme = (theme, persist = false) => {
    const nextTheme = themes.includes(theme) ? theme : 'light';

    document.documentElement.classList.toggle('dark', nextTheme === 'dark');
    document.documentElement.dataset.theme = nextTheme;
    document.documentElement.style.colorScheme = nextTheme;

    if (persist) {
        writeStoredTheme(nextTheme);
    }

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        const isDark = nextTheme === 'dark';
        const actionLabel = isDark ? 'Switch to light mode' : 'Switch to dark mode';
        const icon = toggle.querySelector('[data-theme-icon]');

        toggle.setAttribute('aria-pressed', String(isDark));
        toggle.setAttribute('aria-label', actionLabel);
        toggle.setAttribute('title', actionLabel);
        toggle.dataset.theme = nextTheme;

        if (icon) {
            icon.classList.toggle('bi-moon-stars', !isDark);
            icon.classList.toggle('bi-sun', isDark);
        }
    });
};

const bootTheme = () => {
    const currentTheme = document.documentElement.dataset.theme;
    const storedTheme = readStoredTheme();

    applyTheme(themes.includes(storedTheme) ? storedTheme : currentTheme, false);

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const activeTheme = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
            applyTheme(activeTheme === 'dark' ? 'light' : 'dark', true);
        });
    });

    const mediaQuery = window.matchMedia?.('(prefers-color-scheme: dark)');
    mediaQuery?.addEventListener?.('change', (event) => {
        // A saved preference always wins; otherwise follow the OS preference.
        if (!readStoredTheme()) {
            applyTheme(event.matches ? 'dark' : 'light');
        }
    });
};

const wait = (milliseconds) => new Promise((resolve) => window.setTimeout(resolve, milliseconds));

const redirectToApp = (destination) => {
    window.location.replace(destination);
};

const bootHome = () => {
    if (window.sessionStorage.getItem(splashSessionKey) !== 'true') {
        redirectToApp('/splash');
    }
};

const bootSplash = async () => {
    const alreadyShown = window.sessionStorage.getItem(splashSessionKey) === 'true';

    if (alreadyShown) {
        redirectToApp('/');
        return;
    }

    window.sessionStorage.setItem(splashSessionKey, 'true');

    const minimumDisplay = wait(1800);
    await minimumDisplay;
    document.documentElement.classList.add('splash-is-leaving');
    await wait(420);
    redirectToApp('/');
};

document.addEventListener('DOMContentLoaded', () => {
    const page = document.body.dataset.page;

    bootTheme();

    if (page === 'home') {
        bootHome();
    }

    if (page === 'splash') {
        bootSplash();
    }

    document.querySelectorAll('[data-home-navigation]').forEach((link) => {
        link.addEventListener('click', () => {
            window.sessionStorage.setItem(splashSessionKey, 'true');
        });
    });
});

window.addEventListener('storage', (event) => {
    if (event.key === themeKey && themes.includes(event.newValue)) {
        applyTheme(event.newValue);
    }
});
