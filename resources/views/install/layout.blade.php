<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex, nofollow">
<title>laraBB setup</title>
<style>
:root{--bg:#0a0f1f;--surface:#121a30;--surface2:#1a2444;--border:#27335f;--fg:#e9edfb;--muted:#96a3cc;--accent:#5b8dff;--grad:linear-gradient(120deg,#2f6bff,#8a4dff 60%,#e14fd0);--ok:#4ade80;--warn:#fbbf24;--bad:#fb7185;--glow:#a186ff;color-scheme:dark}
@media (prefers-color-scheme:light){:root{--bg:#f2f5fc;--surface:#fff;--surface2:#e8eefb;--border:#cfd9f0;--fg:#121a33;--muted:#516089;--accent:#2457e6;--grad:linear-gradient(120deg,#2457e6,#7c3aed 60%,#c026d3);--ok:#15803d;--warn:#b45309;--bad:#e11d48;--glow:#7c3aed;color-scheme:light}}
*{box-sizing:border-box}
body{margin:0;background:radial-gradient(120% 60% at 50% 0,color-mix(in srgb,var(--glow) 22%,transparent),transparent 70%),var(--bg);color:var(--fg);font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;min-height:100vh;padding:20px 16px 40px;display:grid;justify-items:center;align-content:start;gap:16px}
.brand{display:flex;align-items:center;gap:10px;font-weight:800;font-size:1.35rem}
.logo{width:36px;height:36px;border-radius:10px;background:var(--grad);display:grid;place-items:center;color:#fff}
.steps{display:flex;gap:6px;width:100%;max-width:640px}
.steps i{flex:1;height:6px;border-radius:99px;background:var(--surface2)}
.steps i.done{background:var(--ok)}
.steps i.cur{background:var(--grad)}
.card{width:100%;max-width:640px;background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:20px;display:grid;gap:16px}
h1{font-size:1.3rem;margin:0}
.lead{margin:0;color:var(--muted);font-size:.95rem}
.field{display:grid;gap:6px}
label{font-size:.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
input,select,textarea{width:100%;min-height:44px;padding:8px 12px;border-radius:11px;border:1px solid var(--border);background:var(--bg);color:var(--fg);font:inherit}
textarea{min-height:110px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.85rem}
input:focus,select:focus,textarea:focus{outline:2px solid var(--accent);outline-offset:1px}
input[type=checkbox]{width:20px;min-height:20px;accent-color:var(--accent)}
.check{display:flex;align-items:center;gap:10px;min-height:44px;padding:0 12px;border:1px solid var(--border);border-radius:11px;text-transform:none;letter-spacing:0;font-size:.95rem;color:var(--fg);font-weight:500}
.two{display:grid;gap:12px}
@media (min-width:560px){.two{grid-template-columns:1fr 1fr}}
.err{color:var(--bad);font-size:.85rem}
.banner{padding:10px 12px;border-radius:11px;font-size:.9rem;border:1px solid}
.banner.ok{background:color-mix(in srgb,var(--ok) 14%,transparent);border-color:color-mix(in srgb,var(--ok) 40%,transparent)}
.banner.bad{background:color-mix(in srgb,var(--bad) 14%,transparent);border-color:color-mix(in srgb,var(--bad) 40%,transparent)}
.hint{font-size:.85rem;color:var(--muted);background:var(--surface2);border-radius:10px;padding:10px 12px}
.hint code,code{font-family:ui-monospace,Menlo,Consolas,monospace;color:var(--fg);word-break:break-all}
.row{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 18px;border-radius:11px;border:1px solid var(--border);background:var(--surface2);color:var(--fg);font:inherit;font-weight:600;cursor:pointer;text-decoration:none}
.btn.pri{background:var(--grad);border-color:transparent;color:#fff}
.btn:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.list{list-style:none;margin:0;padding:0;display:grid;gap:6px}
.list li{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:8px 12px;border:1px solid var(--border);border-radius:11px;font-size:.9rem}
.list .d{margin-left:auto;color:var(--muted);font-size:.8rem}
.dot{width:10px;height:10px;border-radius:50%;flex:none}
.dot.ok{background:var(--ok)}.dot.warn{background:var(--warn)}.dot.fail{background:var(--bad)}
dl{display:grid;grid-template-columns:auto 1fr;gap:6px 14px;margin:0;font-size:.9rem}
dt{color:var(--muted)}dd{margin:0;word-break:break-word}
.tok{font-family:ui-monospace,Menlo,Consolas,monospace;letter-spacing:.2em;text-align:center;font-size:1.1rem}
</style>
</head>
<body>
<div class="brand"><span class="logo">L</span>laraBB setup</div>
@isset($step)
<div class="steps" aria-hidden="true">
    @foreach ($steps as $i => $name)
        <i class="{{ $i < array_search($step, $steps, true) ? 'done' : ($name === $step ? 'cur' : '') }}"></i>
    @endforeach
</div>
@endisset
<main class="card">
    @yield('content')
</main>
</body>
</html>
