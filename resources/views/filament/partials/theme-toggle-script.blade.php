<script>
    (function () {
        var defaultThemeMode = @js(filament()->getDefaultThemeMode()->value);

        function prefersDarkColorScheme() {
            try {
                return window.matchMedia('(prefers-color-scheme: dark)').matches;
            } catch (error) {
                return false;
            }
        }

        function resolveInitialTheme() {
            var storedTheme = null;

            try {
                storedTheme = localStorage.getItem('theme');
            } catch (error) {}

            if (storedTheme === 'light' || storedTheme === 'dark') {
                return storedTheme;
            }

            if (storedTheme === 'system' || defaultThemeMode === 'system') {
                return prefersDarkColorScheme() ? 'dark' : 'light';
            }

            return defaultThemeMode === 'dark' ? 'dark' : 'light';
        }

        function applyGrowthopsTheme(theme) {
            var isLight = theme === 'light';
            var html = document.documentElement;

            html.classList.toggle('light', isLight);
            html.classList.toggle('dark', ! isLight);
            html.setAttribute('data-theme', theme);

            document.querySelectorAll('[data-growthops-theme-toggle]').forEach(function (button) {
                button.setAttribute('aria-checked', isLight ? 'true' : 'false');
            });
        }

        function syncFilamentTheme(theme) {
            try {
                localStorage.setItem('theme', theme);
            } catch (error) {}

            window.dispatchEvent(new CustomEvent('theme-changed', { detail: theme }));
        }

        applyGrowthopsTheme(resolveInitialTheme());

        document.querySelectorAll('[data-growthops-theme-toggle]').forEach(function (button) {
            button.addEventListener('click', function () {
                var nextTheme = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';

                applyGrowthopsTheme(nextTheme);
                syncFilamentTheme(nextTheme);
            });
        });
    })();
</script>
