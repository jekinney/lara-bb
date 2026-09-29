<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>@yield('title', config('app.name'))</title>
<style>
:root{--bg:#0a0f1f;--surface:#121a30;--surface2:#1a2444;--border:#27335f;--fg:#e9edfb;--muted:#96a3cc;--accent:#5b8dff;--grad:linear-gradient(120deg,#2f6bff,#8a4dff 60%,#e14fd0);--c1:#5b8dff;--c2:#a186ff;--c3:#2dd4bf;--c4:#fbbf24;--c5:#fb7185;--c6:#4ade80;--display:"Sora",system-ui,sans-serif;color-scheme:dark}
@media (prefers-color-scheme:light){:root{--bg:#f2f5fc;--surface:#fff;--surface2:#e8eefb;--border:#cfd9f0;--fg:#121a33;--muted:#516089;--accent:#2457e6;--grad:linear-gradient(120deg,#2457e6,#7c3aed 60%,#c026d3);--c1:#2457e6;--c2:#7c3aed;--c3:#0d9488;--c4:#b45309;--c5:#e11d48;--c6:#15803d;color-scheme:light}}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--fg);font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;min-height:100vh;display:flex;flex-direction:column;padding-bottom:64px}
a{color:var(--accent);text-decoration:none}
a:hover{text-decoration:underline}
:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
.top{display:flex;align-items:center;gap:10px;padding:10px 16px;background:var(--surface);border-bottom:1px solid var(--border)}
.brand{display:flex;align-items:center;gap:9px;font-weight:800;font-size:1.1rem;color:var(--fg)}
.brand:hover{text-decoration:none}
.logo{width:30px;height:30px;border-radius:9px;background:var(--grad);display:grid;place-items:center;color:#fff}
.sp{flex:1}
.nav{display:none;gap:4px;padding:0 16px;background:var(--surface);border-bottom:1px solid var(--border)}
.nav a{padding:11px 12px;color:var(--muted);font-weight:600;font-size:.92rem}
.nav a[aria-current]{color:var(--fg);box-shadow:inset 0 -3px 0 var(--accent)}
.only-wide{display:none}
.tabbar{position:fixed;left:0;right:0;bottom:0;display:grid;grid-template-columns:repeat(3,1fr);background:var(--surface);border-top:1px solid var(--border);padding-bottom:env(safe-area-inset-bottom,0px)}
.tabbar a,.tabbar button{display:grid;justify-items:center;padding:10px 0;color:var(--muted);font:inherit;font-size:.78rem;font-weight:600;background:none;border:0;cursor:pointer;min-height:48px}
.tabbar [aria-current]{color:var(--accent)}
@media (min-width:720px){.nav{display:flex}.tabbar{display:none}.only-wide{display:inline-flex}body{padding-bottom:0}}
main{width:100%;max-width:960px;margin:0 auto;padding:16px;display:grid;gap:16px;align-content:start;flex:1}
.card{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:20px;display:grid;gap:16px}
.narrow{max-width:520px;margin-inline:auto;width:100%}
h1{font-size:1.4rem;margin:0;font-family:var(--display)}
h2{font-size:1.05rem;margin:0}
.lead,.muted{color:var(--muted);font-size:.92rem;margin:0}
.field{display:grid;gap:6px}
label{font-size:.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
input,select,textarea{width:100%;min-height:44px;padding:8px 12px;border-radius:11px;border:1px solid var(--border);background:var(--bg);color:var(--fg);font:inherit}
textarea{min-height:110px}
input[type=checkbox]{width:20px;min-height:20px;accent-color:var(--accent)}
.check{display:flex;align-items:center;gap:10px;text-transform:none;letter-spacing:0;font-size:.95rem;color:var(--fg);font-weight:500}
.two{display:grid;gap:12px}
@media (min-width:560px){.two{grid-template-columns:1fr 1fr}}
.err{color:var(--c5);font-size:.85rem}
.banner{padding:10px 14px;border-radius:11px;font-size:.92rem;background:color-mix(in srgb,var(--c6) 14%,transparent);border:1px solid color-mix(in srgb,var(--c6) 40%,transparent)}
.hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.btn{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:0 18px;border-radius:11px;border:1px solid var(--border);background:var(--surface2);color:var(--fg);font:inherit;font-weight:600;cursor:pointer}
.btn:hover{text-decoration:none}
.btn.pri{background:var(--grad);border-color:transparent;color:#fff}
.btn.sm{min-height:36px;padding:0 12px;font-size:.88rem}
.row{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;align-items:center}
.av{width:44px;height:44px;border-radius:50%;background:var(--c);color:#0a0f1f;display:grid;place-items:center;font-weight:800;flex:none}
.av.lg{width:72px;height:72px;font-size:1.3rem}
.tone-1{--c:var(--c1)}.tone-2{--c:var(--c2)}.tone-3{--c:var(--c3)}.tone-4{--c:var(--c4)}.tone-5{--c:var(--c5)}.tone-6{--c:var(--c6)}
.pill{display:inline-flex;font-size:.72rem;font-weight:700;padding:2px 9px;border-radius:99px;color:var(--c,var(--accent));background:color-mix(in srgb,var(--c,var(--accent)) 18%,transparent);border:1px solid color-mix(in srgb,var(--c,var(--accent)) 40%,transparent)}
.list{list-style:none;margin:0;padding:0;display:grid;gap:8px}
.list li{display:flex;gap:12px;align-items:center;padding:10px 12px;border:1px solid var(--border);border-radius:12px}
.who{display:flex;gap:16px;align-items:center;flex-wrap:wrap}
dl{display:grid;grid-template-columns:auto 1fr;gap:6px 14px;margin:0;font-size:.92rem}
dt{color:var(--muted)}dd{margin:0;word-break:break-word}
footer{padding:16px;text-align:center;color:var(--muted);font-size:.82rem}
.pager{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
.pager a,.pager span{min-width:40px;min-height:40px;display:grid;place-items:center;padding:0 10px;border-radius:10px;border:1px solid var(--border);background:var(--surface)}
.pager span[aria-current]{background:var(--accent);color:#fff;border-color:transparent}
</style>
</head>
<body>
<header class="top">
    <a class="brand" href="{{ route('home') }}"><span class="logo">L</span>{{ config('app.name') }}</a>
    <span class="sp"></span>
    @auth
        <a class="btn sm only-wide" href="{{ route('members.show', auth()->user()->name) }}">{{ auth()->user()->name }}</a>
        <form method="post" action="{{ route('logout') }}" class="only-wide">@csrf<button class="btn sm" type="submit">Log out</button></form>
    @else
        <a class="btn sm only-wide" href="{{ route('login') }}">Log in</a>
        <a class="btn sm pri only-wide" href="{{ route('register') }}">Register</a>
    @endauth
</header>
<nav class="nav" aria-label="Primary">
    <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>Board index</a>
    <a href="{{ route('members.index') }}" @if (request()->routeIs('members.*')) aria-current="page" @endif>Members</a>
    @auth
        <a href="{{ route('account') }}" @if (request()->routeIs('account*')) aria-current="page" @endif>User control panel</a>
    @endauth
</nav>
<main>
    @if (session('status'))
        <div class="banner" role="status">{{ session('status') }}</div>
    @endif
    @yield('content')
</main>
<footer>{{ config('app.name') }}, powered by laraBB</footer>
<nav class="tabbar" aria-label="Mobile">
    <a href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>Home</a>
    <a href="{{ route('members.index') }}" @if (request()->routeIs('members.*')) aria-current="page" @endif>Members</a>
    @auth
        <a href="{{ route('account') }}" @if (request()->routeIs('account*')) aria-current="page" @endif>Me</a>
    @else
        <a href="{{ route('login') }}" @if (request()->routeIs('login', 'register')) aria-current="page" @endif>Log in</a>
    @endauth
</nav>
</body>
</html>
