@php
    $meta = [
        'google' => ['label' => 'Google', 'bg' => '#ffffff', 'fg' => '#4285F4'],
        'meta' => ['label' => 'Meta', 'bg' => '#0668E1', 'fg' => '#ffffff'],
        'tiktok' => ['label' => 'TikTok', 'bg' => '#000000', 'fg' => '#25F4EE'],
        'taboola' => ['label' => 'Taboola', 'bg' => '#1E1E1E', 'fg' => '#FF6E00'],
    ][$platform] ?? ['label' => ucfirst($platform), 'bg' => '#64748b', 'fg' => '#ffffff'];
@endphp
<div class="inline-flex items-center gap-2">
    <span
        style="display:inline-flex; align-items:center; justify-content:center; width:1.5rem; height:1.5rem; border-radius:9999px; background:{{ $meta['bg'] }}; border: 1px solid rgba(148,163,184,0.35); font-weight:700; font-size:0.6875rem; color:{{ $meta['fg'] }}; flex-shrink:0;"
        title="{{ $meta['label'] }}"
    >{{ mb_substr($meta['label'], 0, 1) }}</span>
    <span>{{ $meta['label'] }}</span>
</div>
