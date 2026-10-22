<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    @stack('head')
    <title>@yield('title') · Laravel Status</title>
    <style>
        :root {
            --bg: #f6f7f9; --panel: #fff; --text: #1d2330; --muted: #667085; --line: #e4e7ec;
            --ok: #12805c; --ok-bg: #e7f6ef; --warn: #a15c07; --warn-bg: #fef4e4;
            --down: #b42318; --down-bg: #fdecea; --off: #475467; --off-bg: #eef0f3; --accent: #2457c5;
            color-scheme: light;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f1218; --panel: #171b23; --text: #e6e8ec; --muted: #98a2b3; --line: #2a303b;
                --ok: #4ccf9a; --ok-bg: #12312a; --warn: #f2b65a; --warn-bg: #3a2a12;
                --down: #ff8a80; --down-bg: #3d1a18; --off: #c0c6d0; --off-bg: #262b35; --accent: #8fb0ff;
                color-scheme: dark;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--text); font: 14px/1.45 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }
        .wrap { max-width: 1400px; margin: 0 auto; padding: 24px 16px 48px; }
        header { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 8px 24px; margin-bottom: 20px; }
        h1 { font-size: 20px; margin: 0; }
        .meta { color: var(--muted); font-size: 13px; }
        .meta a { color: var(--accent); }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 20px; }
        .tile { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 12px 16px; }
        .tile b { display: block; font-size: 24px; font-variant-numeric: tabular-nums; }
        .tile span { color: var(--muted); font-size: 13px; }
        .tile.ok b { color: var(--ok); } .tile.degraded b { color: var(--warn); }
        .tile.down b { color: var(--down); } .tile.unreachable b { color: var(--off); }
        .table-wrap { overflow-x: auto; background: var(--panel); border: 1px solid var(--line); border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; font-variant-numeric: tabular-nums; }
        th, td { text-align: left; padding: 10px 12px; border-bottom: 1px solid var(--line); white-space: nowrap; vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--muted); font-weight: 600; }
        tr:last-child > td { border-bottom: 0; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .badge.ok { color: var(--ok); background: var(--ok-bg); } .badge.degraded { color: var(--warn); background: var(--warn-bg); }
        .badge.down { color: var(--down); background: var(--down-bg); } .badge.unreachable { color: var(--off); background: var(--off-bg); }
        .app a { color: var(--text); font-weight: 600; text-decoration: none; }
        .app small, .sub { display: block; color: var(--muted); font-size: 12px; }
        .behind { color: var(--warn); font-weight: 600; }
        .behind::after { content: " ↓"; }
        .bad { color: var(--down); font-weight: 600; }
        .muted { color: var(--muted); }
        code { font: 12px ui-monospace, SFMono-Regular, Menlo, monospace; }
        ul.issues { margin: 4px 0 0; padding-left: 16px; color: var(--warn); font-size: 12px; white-space: normal; }
        details summary { cursor: pointer; color: var(--accent); font-size: 12px; }
        details pre { max-width: 640px; max-height: 360px; overflow: auto; background: var(--bg); padding: 8px; border-radius: 6px; font-size: 11px; white-space: pre; }
        .legend { margin-top: 12px; color: var(--muted); font-size: 12px; }
        nav { display: flex; gap: 4px; margin-bottom: 20px; border-bottom: 1px solid var(--line); }
        nav a { padding: 8px 12px; color: var(--muted); text-decoration: none; font-weight: 600; border-bottom: 2px solid transparent; margin-bottom: -1px; }
        nav a.active { color: var(--text); border-bottom-color: var(--accent); }
        nav .brand { color: var(--text); margin-right: auto; padding-left: 0; }
        .flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; background: var(--ok-bg); color: var(--ok); }
        .flash.error { background: var(--down-bg); color: var(--down); }
        .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 16px; margin-bottom: 20px; }
        h2 { font-size: 16px; margin: 0 0 12px; }
        form.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; align-items: end; }
        label { display: block; font-size: 12px; font-weight: 600; color: var(--muted); margin-bottom: 4px; }
        label.inline { display: flex; align-items: center; gap: 6px; font-size: 14px; color: var(--text); font-weight: 400; margin: 0; }
        input[type=text], input[type=url], input[type=password] { width: 100%; padding: 8px 10px; border: 1px solid var(--line); border-radius: 6px; background: var(--bg); color: var(--text); font: inherit; }
        input:focus { outline: 2px solid var(--accent); outline-offset: -1px; }
        .hint { color: var(--muted); font-size: 12px; margin-top: 4px; }
        .field-error { color: var(--down); font-size: 12px; margin-top: 4px; }
        button, .button { display: inline-block; padding: 8px 14px; border: 0; border-radius: 6px; background: var(--accent); color: var(--panel); font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        button.secondary, .button.secondary { background: var(--off-bg); color: var(--text); }
        button.danger { background: transparent; color: var(--down); padding: 4px 8px; }
        .actions { display: flex; gap: 8px; align-items: center; }
        .actions form { margin: 0; }
        td a { color: var(--accent); }
        .empty { color: var(--muted); padding: 16px 12px; }
    </style>
</head>
<body>
<div class="wrap">
    <nav>
        <a class="brand" href="{{ route('status') }}">Laravel Status</a>
        <a href="{{ route('status') }}" @class(['active' => request()->routeIs('status')])>Dashboard</a>
        <a href="{{ route('apps.index') }}" @class(['active' => request()->routeIs('apps.*')])>Applications</a>
    </nav>

    @if (session('success'))
        <div class="flash">{{ session('success') }}</div>
    @endif

    @yield('content')
</div>
</body>
</html>
