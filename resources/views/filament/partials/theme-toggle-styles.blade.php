<style>
    .growthops-brand {
        display: inline-flex;
        align-items: center;
        gap: 0.625rem;
    }

    .growthops-brand__logo {
        height: 1.75rem;
        width: auto;
    }

    .growthops-brand__logo--dark { display: none; }
    .growthops-brand__logo--light { display: none; }

    [data-theme="dark"] .growthops-brand__logo--dark { display: block; }
    [data-theme="light"] .growthops-brand__logo--light { display: block; }

    .growthops-brand__label {
        font-weight: 600;
        font-size: 0.9375rem;
        letter-spacing: -0.01em;
        color: rgb(226 232 240);
    }

    [data-theme="light"] .growthops-brand__label {
        color: rgb(30 41 59);
    }

    /* Hide Filament's own built-in switcher — the custom toggle above replaces it,
       but stays mounted via localStorage['theme'] + a theme-changed event. */
    .fi-theme-switcher {
        display: none;
    }

    /* The sidebar collapse/expand toggle moves out of the topbar entirely and
       onto the sidebar's own footer — see sidebar-collapse-toggle.blade.php. */
    .fi-topbar-collapse-sidebar-btn-ctn {
        display: none;
    }

    .growthops-sidebar-toggle-ctn {
        padding: 0.5rem 0.75rem;
        border-top: 1px solid rgba(148, 163, 184, 0.15);
    }

    .growthops-sidebar-toggle {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        width: 100%;
        padding: 0.5rem;
        border-radius: 0.5rem;
        border: none;
        background: transparent;
        color: rgb(148 163 184);
        font-size: 0.8125rem;
        font-weight: 500;
        cursor: pointer;
        transition: background-color 100ms;
    }

    .growthops-sidebar-toggle:hover {
        background: rgba(148, 163, 184, 0.1);
    }

    .growthops-sidebar-toggle--collapsed {
        padding: 0.5rem 0;
    }

    .growthops-theme-toggle {
        position: relative;
        display: inline-flex;
        height: 1.5rem;
        width: 2.75rem;
        flex-shrink: 0;
        cursor: pointer;
        align-items: center;
        border-radius: 9999px;
        border: 1px solid rgb(71 85 105);
        background-color: rgb(30 41 59);
        transition: background-color 150ms, border-color 150ms;
        outline-offset: 2px;
    }

    [data-theme="light"] .growthops-theme-toggle {
        border-color: rgb(203 213 225);
        background-color: rgb(241 245 249);
    }

    .growthops-theme-toggle:focus-visible {
        outline: 2px solid rgb(245 158 11);
    }

    .growthops-theme-toggle__knob {
        pointer-events: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 1.125rem;
        width: 1.125rem;
        border-radius: 9999px;
        background-color: rgb(226 232 240);
        color: rgb(15 23 42);
        transform: translateX(0.1875rem);
        transition: transform 150ms, background-color 150ms;
    }

    [data-theme="light"] .growthops-theme-toggle__knob {
        background-color: rgb(15 23 42);
        color: rgb(241 245 249);
        transform: translateX(1.4375rem);
    }

    .growthops-theme-toggle__icon {
        position: absolute;
    }

    .growthops-theme-toggle__icon--sun { opacity: 0; }
    .growthops-theme-toggle__icon--moon { opacity: 1; }

    [data-theme="light"] .growthops-theme-toggle__icon--sun { opacity: 1; }
    [data-theme="light"] .growthops-theme-toggle__icon--moon { opacity: 0; }

    /* AI reasoning: styled as visibly distinct "thinking" output — a monospace,
       italic, dim block — so it never reads as just another narrative paragraph. */
    .growthops-reasoning {
        font-family: ui-monospace, 'JetBrains Mono', 'SFMono-Regular', Menlo, monospace;
        font-style: italic;
        font-size: 0.8125rem;
        line-height: 1.6;
        color: rgb(148 163 184);
        background: rgba(100, 116, 139, 0.08);
        border-left: 3px solid rgb(245 158 11);
        padding: 0.875rem 1rem;
        border-radius: 0.375rem;
        white-space: pre-wrap;
    }
</style>
