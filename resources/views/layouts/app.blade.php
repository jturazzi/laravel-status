<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title') · Laravel Status</title>
    <style>
        :root {
            --bg: #eef1f4; --panel: #ffffff; --ink: #18212b; --muted: #5b6875; --line: #d3d9e0; --hover: #f5f7f9;
            --ok: #1f7a4d; --ok-soft: #e3f2ea; --warn: #b4690e; --warn-soft: #fbefdc;
            --down: #c2362b; --down-soft: #fbe5e3; --off: #7a8591; --off-soft: #e7eaee; --accent: #2f5bd3;
            color-scheme: light;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #12171d; --panel: #1a2129; --ink: #e3e8ee; --muted: #94a0ad; --line: #2c3540; --hover: #212932;
                --ok: #4cc58a; --ok-soft: #173326; --warn: #eaa64a; --warn-soft: #3a2c16;
                --down: #f07b70; --down-soft: #3d1d1b; --off: #8e99a6; --off-soft: #262e38; --accent: #86a6ff;
                color-scheme: dark;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 15px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; font-variant-numeric: tabular-nums; }
        a { color: var(--accent); }
        :focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; border-radius: 4px; }
        code { font: 13px ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; }
        [hidden] { display: none !important; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; scroll-behavior: auto !important; } }

        .wrap { max-width: 1320px; margin: 0 auto; padding: 20px 20px 64px; }
        .topbar { display: flex; align-items: center; gap: 4px; margin-bottom: 28px; }
        .topbar .brand .version { color: var(--muted); font-weight: 400; font-size: 13px; margin-left: 4px; }
        .topbar .brand { margin-right: auto; white-space: nowrap; font-weight: 700; color: var(--ink); text-decoration: none; letter-spacing: -.01em; }
        .topbar a.tab { padding: 6px 12px; border-radius: 6px; color: var(--muted); text-decoration: none; font-weight: 600; }
        .topbar a.tab:hover { color: var(--ink); }
        .topbar a.tab[aria-current="page"] { color: var(--ink); background: var(--panel); box-shadow: inset 0 0 0 1px var(--line); }
        .langs { display: inline-flex; margin-left: 10px; padding-left: 12px; border-left: 1px solid var(--line); gap: 2px; }
        .langs a { padding: 4px 7px; border-radius: 5px; font-size: 12px; font-weight: 600; color: var(--muted); text-decoration: none; }
        .langs a:hover { color: var(--ink); }
        .langs a[aria-current="true"] { color: var(--ink); background: var(--panel); box-shadow: inset 0 0 0 1px var(--line); }

        /* Status colour carried by one custom property */
        .is-ok { --status: var(--ok); --status-soft: var(--ok-soft); }
        .is-degraded { --status: var(--warn); --status-soft: var(--warn-soft); }
        .is-down { --status: var(--down); --status-soft: var(--down-soft); }
        .is-unreachable { --status: var(--off); --status-soft: var(--off-soft); }
        .pill { display: inline-block; padding: 1px 9px; border-radius: 999px; font-size: 13px; font-weight: 600; color: var(--status); background: var(--status-soft); }

        .notice { display: flex; gap: 10px; align-items: baseline; padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; background: var(--panel); border: 1px solid var(--line); border-left: 4px solid var(--status, var(--ok)); }
        .notice p { margin: 0; }

        .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 18px 20px; margin-bottom: 24px; }
        h1 { font-size: 22px; margin: 0 0 4px; letter-spacing: -.01em; }
        h2 { font-size: 17px; margin: 0 0 14px; }
        .lead { margin: 0 0 20px; color: var(--muted); max-width: 70ch; }

        form.fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; align-items: end; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; }
        label .opt { color: var(--muted); font-weight: 400; }
        label.check { display: flex; align-items: center; gap: 8px; font-weight: 400; font-size: 14px; margin: 6px 0; }
        input[type=text], input[type=url], input[type=password], input[type=search] { width: 100%; padding: 8px 10px; border: 1px solid var(--line); border-radius: 6px; background: var(--bg); color: var(--ink); font: inherit; }
        .hint { color: var(--muted); font-size: 13px; margin: 12px 0 0; }
        .field-error { color: var(--down); font-size: 13px; margin-top: 4px; }
        button, .button { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border: 1px solid transparent; border-radius: 6px; background: var(--accent); color: #fff; font: inherit; font-weight: 600; cursor: pointer; text-decoration: none; }
        .button.quiet, button.quiet { background: transparent; color: var(--ink); border-color: var(--line); }
        .button.quiet:hover, button.quiet:hover { background: var(--hover); }
        button.link { background: none; border: 0; padding: 0; color: var(--accent); font-weight: 600; }
        button.link.danger { color: var(--down); }
        .actions { display: flex; gap: 14px; align-items: center; justify-content: flex-end; }
        .actions form { margin: 0; }

        .table-wrap { overflow-x: auto; background: var(--panel); border: 1px solid var(--line); border-radius: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 11px 14px; border-bottom: 1px solid var(--line); vertical-align: middle; }
        th { font-size: 13px; color: var(--muted); font-weight: 600; }
        tbody tr:last-child td { border-bottom: 0; }
        .muted { color: var(--muted); }
        .empty { padding: 28px 16px; color: var(--muted); }

        @media (max-width: 720px) {
            .wrap { padding: 14px 14px 48px; }
            .topbar { flex-wrap: wrap; row-gap: 8px; margin-bottom: 20px; }
            .topbar .brand { flex: 1 1 100%; margin-right: 0; }
            .topbar a.tab { padding: 8px 12px; }
            .langs { margin-left: auto; padding-left: 8px; }
            .langs a { padding: 8px 9px; }
            .panel { padding: 16px; }
            input[type=text], input[type=url], input[type=password], input[type=search] { font-size: 16px; padding: 10px 12px; }
            button, .button { padding: 10px 16px; }
            form.fields { grid-template-columns: 1fr; }
            form.fields button[type=submit] { width: 100%; justify-content: center; }
            .notice { flex-direction: column; gap: 2px; }

            /* Table rows become stacked cards */
            .table-wrap { background: none; border: 0; border-radius: 0; overflow: visible; }
            table.stack, table.stack tbody, table.stack tr, table.stack td { display: block; width: 100%; }
            table.stack thead { display: none; }
            table.stack tr { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; padding: 8px 14px; margin-bottom: 12px; }
            table.stack td { display: flex; gap: 12px; justify-content: space-between; align-items: baseline; padding: 6px 0; border-bottom: 0; overflow-wrap: anywhere; text-align: right; }
            table.stack td[data-label]::before { content: attr(data-label); flex: none; color: var(--muted); font-size: 13px; font-weight: 600; text-align: left; }
            table.stack td.card-actions { display: block; padding-top: 10px; margin-top: 4px; border-top: 1px solid var(--line); }
            table.stack td.card-actions .actions { justify-content: flex-start; flex-wrap: wrap; gap: 8px 20px; }
            table.stack td.card-actions button.link, table.stack td.card-actions a { padding: 6px 0; }
            table.stack td.empty { display: block; text-align: left; }
        }
    </style>
    @stack('head')
</head>
<body>
<div class="wrap">
    <nav class="topbar" aria-label="{{ __('Main') }}">
        <a class="brand" href="{{ route('status') }}">Laravel Status <span class="version">{{ config('app.version') }}</span></a>
        <a class="tab" href="{{ route('status') }}" @if (request()->routeIs('status')) aria-current="page" @endif>{{ __('Dashboard') }}</a>
        <span class="langs" role="group" aria-label="{{ __('Language') }}">
            @foreach (\App\Http\Middleware\SetLocale::LOCALES as $locale)
                <a href="{{ request()->fullUrlWithQuery(['lang' => $locale]) }}" lang="{{ $locale }}" hreflang="{{ $locale }}" @if (app()->getLocale() === $locale) aria-current="true" @endif title="{{ ['en' => 'English', 'fr' => 'Français'][$locale] }}">{{ strtoupper($locale) }}</a>
            @endforeach
        </span>
    </nav>

    @if (session('success'))
        <div class="notice is-ok" role="status"><p>{{ session('success') }}</p></div>
    @endif
    @if (session('check'))
        <div class="notice is-{{ session('check.status') }}" role="status"><p>{{ session('check.message') }}</p></div>
    @endif

    @yield('content')
</div>
@stack('scripts')
</body>
</html>
