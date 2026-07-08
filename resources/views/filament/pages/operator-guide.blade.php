<x-filament-panels::page>
    <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">What this guide can do</h3>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            An authenticated, allowlisted helper for repetitive campaign-ops. Read-only workflows answer questions;
            the campaign-brief workflow drafts only and never submits without your explicit confirmation.
        </p>

        <ul class="mt-4 space-y-2">
            @foreach ($this->allowedWorkflows as $key => $workflow)
                <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                    <span class="fi-badge inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium
                        {{ $workflow['mode'] === 'read' ? 'bg-primary-50 text-primary-700 dark:bg-primary-400/10 dark:text-primary-400' : 'bg-warning-50 text-warning-700 dark:bg-warning-400/10 dark:text-warning-400' }}">
                        {{ $workflow['mode'] === 'read' ? 'read-only' : 'confirm to save' }}
                    </span>
                    <span class="font-medium">{{ $workflow['label'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    @if ($result)
        <div class="fi-section rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Result</h3>
            <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">{{ $result['summary'] }}</p>

            <pre class="mt-4 overflow-x-auto rounded-lg bg-gray-50 p-4 text-xs text-gray-700 dark:bg-gray-950/50 dark:text-gray-300">{{ json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    @endif
</x-filament-panels::page>
