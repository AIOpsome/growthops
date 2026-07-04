<div class="growthops-sidebar-toggle-ctn" x-data="{}">
    <button
        type="button"
        x-cloak
        x-on:click="$store.sidebar.close()"
        x-show="$store.sidebar.isOpen"
        class="growthops-sidebar-toggle"
    >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
        <span>Collapse</span>
    </button>
    <button
        type="button"
        x-cloak
        x-on:click="$store.sidebar.open()"
        x-show="! $store.sidebar.isOpen"
        class="growthops-sidebar-toggle growthops-sidebar-toggle--collapsed"
    >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
    </button>
</div>
