@php($themeToggleClass = trim('theme-toggle '.($class ?? '')))

<button
    class="{{ $themeToggleClass }}"
    type="button"
    data-theme-toggle
    aria-pressed="false"
    aria-label="Switch to dark mode"
    title="Switch to dark mode"
>
    <i class="bi bi-moon-stars" data-theme-icon aria-hidden="true"></i>
</button>
