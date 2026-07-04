<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GrowthOps — Demo Reset</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #0f172a; color: #e2e8f0; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: #1e293b; border-radius: 12px; padding: 2.5rem; max-width: 32rem; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.4); }
        h1 { font-size: 1.25rem; margin: 0 0 0.5rem; }
        p { color: #94a3b8; line-height: 1.5; }
        button { background: #dc2626; color: white; border: none; border-radius: 8px; padding: 0.75rem 1.5rem; font-size: 1rem; font-weight: 600; cursor: pointer; margin-top: 1rem; }
        button:hover { background: #b91c1c; }
        .status { background: #14532d; color: #bbf7d0; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; font-family: monospace; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>GrowthOps — Demo Reset</h1>
        <p>Wipes all campaigns, actions, leads, and audit history, then rebuilds the canonical 7-campaign demo story. Use this between rehearsal takes so any CSV you upload always lands on a clean, known state.</p>
        @if (session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif
        <form method="POST" action="{{ route('demo-reset.reset', ['token' => request()->route('token')]) }}"
              onsubmit="return confirm('This will wipe all current demo data. Continue?');">
            @csrf
            <button type="submit">Reset demo data</button>
        </form>
    </div>
</body>
</html>
