@php
    $labels = [
        'google' => 'Google',
        'meta' => 'Meta',
        'tiktok' => 'TikTok',
        'taboola' => 'Taboola',
    ];
    $label = $labels[$platform] ?? ucfirst($platform);
    $iconPath = in_array($platform, ['google', 'meta', 'tiktok'], true)
        ? asset("images/platforms/{$platform}.svg")
        : null;
@endphp
<div class="inline-flex items-center gap-2">
    @if ($iconPath)
        <span
            style="display:inline-flex; align-items:center; justify-content:center; width:1.5rem; height:1.5rem; border-radius:9999px; background:#ffffff; border: 1px solid rgba(148,163,184,0.35); flex-shrink:0; padding:0.25rem;"
            title="{{ $label }}"
        >
            <img src="{{ $iconPath }}" alt="{{ $label }}" style="width:100%; height:100%; object-fit:contain;" />
        </span>
    @else
        <span
            style="display:inline-flex; align-items:center; justify-content:center; width:1.5rem; height:1.5rem; border-radius:9999px; background:#1E1E1E; border: 1px solid rgba(148,163,184,0.35); font-weight:700; font-size:0.6875rem; color:#FF6E00; flex-shrink:0;"
            title="{{ $label }}"
        >{{ mb_substr($label, 0, 1) }}</span>
    @endif
    <span>{{ $label }}</span>
</div>
