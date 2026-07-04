@php
    $size = 120;
    $thickness = 12;
    $radius = ($size - $thickness) / 2;
    $circumference = 2 * M_PI * $radius;
    $pct = max(0, min(1, (float) $confidence));
    $dash = round($circumference * $pct, 2);
    $percentValue = round($pct * 100);
    $tone = match (true) {
        $percentValue >= 80 => '#10b981',
        $percentValue >= 60 => '#3b82f6',
        $percentValue >= 40 => '#f59e0b',
        default => '#ef4444',
    };
@endphp
<div class="inline-flex flex-col items-center gap-1">
    <div class="relative" style="width: {{ $size }}px; height: {{ $size }}px;">
        <svg width="{{ $size }}" height="{{ $size }}" style="transform: rotate(-90deg);">
            <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}" fill="none" stroke="rgba(148, 163, 184, 0.25)" stroke-width="{{ $thickness }}" />
            <circle cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $radius }}" fill="none" stroke="{{ $tone }}" stroke-width="{{ $thickness }}"
                stroke-linecap="round" stroke-dasharray="{{ $dash }} {{ round($circumference - $dash, 2) }}" />
        </svg>
        <div class="absolute inset-0 flex flex-col items-center justify-center">
            <span style="font-size: 1.75rem; font-weight: 700; line-height: 1;">{{ $percentValue }}%</span>
            <span style="font-size: 0.6875rem; color: rgb(148 163 184); margin-top: 0.125rem;">confidence</span>
        </div>
    </div>
</div>
