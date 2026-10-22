@php
    $bytes = function (?int $b): string {
        if ($b === null) return '—';
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = $b > 0 ? min((int) floor(log($b, 1024)), 4) : 0;
        return round($b / 1024 ** $i, 1).' '.$u[$i];
    };
    $labels = ['ok' => 'OK', 'degraded' => 'Degraded', 'down' => 'Down', 'unreachable' => 'Unreachable'];
    $behind = fn (?string $v, ?string $latest) => $v && $latest && version_compare($v, $latest, '<');
    $order = ['down' => 0, 'unreachable' => 1, 'degraded' => 2, 'ok' => 3];
    $sorted = collect($apps)->sortBy(fn ($a, $k) => [$order[$a['status']] ?? 9, $a['name']]);
@endphp
@extends('layouts.app')

@section('title', 'Dashboard')

@push('head')
    <meta http-equiv="refresh" content="60">
@endpush

@section('content')
    <header>
        <h1>Application status</h1>
        <div class="meta">
            @php($checked = \Illuminate\Support\Carbon::parse($checked_at)->setTimezone(config('app.timezone')))
            Checked {{ abs($checked->diffInSeconds()) < 5 ? 'just now' : $checked->diffForHumans() }}
            ({{ $checked->format('Y-m-d H:i:s') }})
            · <a href="?refresh=1">Refresh</a> · <a href="{{ route('status.json') }}">JSON</a>
        </div>
    </header>

    <section class="summary">
        @foreach (['ok', 'degraded', 'down', 'unreachable'] as $s)
            <div class="tile {{ $s }}"><b>{{ $summary[$s] ?? 0 }}</b><span>{{ $labels[$s] }}</span></div>
        @endforeach
        <div class="tile"><b>{{ $latest['php'] ?? '—' }}</b><span>Latest PHP</span></div>
        <div class="tile"><b>{{ $latest['laravel'] ?? '—' }}</b><span>Latest Laravel</span></div>
    </section>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Application</th>
                <th>Status</th>
                <th>Version</th>
                <th>Env</th>
                <th>PHP</th>
                <th>Laravel</th>
                <th>Database</th>
                <th>Migr.</th>
                <th>Queue</th>
                <th>Free disk</th>
                <th>Response</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @forelse ($sorted as $key => $app)
                @php($r = $app['report'])
                <tr>
                    <td class="app">
                        <a href="{{ $app['url'] }}" target="_blank" rel="noopener">{{ $app['name'] }}</a>
                        <small>{{ preg_replace('#^https?://#', '', $app['url']) }}</small>
                    </td>
                    <td>
                        <span class="badge {{ $app['status'] }}">{{ $labels[$app['status']] ?? $app['status'] }}</span>
                        @if ($app['error'])
                            <ul class="issues"><li>{{ $app['error'] }}</li></ul>
                        @elseif (! empty($r['issues']))
                            <ul class="issues">
                                @foreach ($r['issues'] as $issue)<li>{{ $issue['message'] }}</li>@endforeach
                            </ul>
                        @endif
                    </td>
                    @if ($r)
                        <td>
                            {{ $r['app']['version'] ?? '—' }}
                            <span class="sub">
                                @if ($r['app']['commit'])<code>{{ \Illuminate\Support\Str::limit($r['app']['commit'], 8, '') }}</code>@else unknown commit @endif
                                @if ($r['app']['build_date']) · {{ \Illuminate\Support\Carbon::parse($r['app']['build_date'])->setTimezone(config('app.timezone'))->format('Y-m-d H:i') }}@endif
                            </span>
                        </td>
                        <td>
                            <span @class(['bad' => $r['app']['environment'] === 'production' && $r['app']['debug']])>{{ $r['app']['environment'] }}</span>
                            @if ($r['app']['debug'])<span class="sub">debug</span>@endif
                        </td>
                        <td @class(['behind' => $behind($r['runtime']['php'], $latest['php'])])>{{ $r['runtime']['php'] }}</td>
                        <td @class(['behind' => $behind($r['runtime']['laravel'], $latest['laravel'])])>{{ $r['runtime']['laravel'] }}</td>
                        <td>
                            @if ($r['database']['ok'])
                                {{ ['mysql' => 'MariaDB/MySQL', 'mariadb' => 'MariaDB', 'sqlite' => 'SQLite', 'pgsql' => 'PostgreSQL'][$r['database']['driver']] ?? $r['database']['driver'] }}
                                {{ $r['database']['version'] ?? '' }}
                                <span class="sub">{{ $bytes($r['database']['size_bytes'] ?? null) }} · {{ $r['database']['latency_ms'] }} ms</span>
                            @else
                                <span class="bad">{{ $r['database']['driver'] }} down</span>
                                <span class="sub">{{ $r['database']['error'] ?? '' }}</span>
                            @endif
                        </td>
                        <td @class(['behind' => ($r['database']['pending_migrations'] ?? 0) > 0])>{{ $r['database']['pending_migrations'] ?? '—' }}</td>
                        <td>
                            {{ $r['queue']['pending'] ?? '—' }} pending
                            <span @class(['sub', 'bad' => ($r['queue']['failed'] ?? 0) > 0])>{{ $r['queue']['failed'] ?? '—' }} failed</span>
                        </td>
                        <td>
                            {{ $r['storage']['disk_free_percent'] !== null ? $r['storage']['disk_free_percent'].'%' : '—' }}
                            <span class="sub">{{ $bytes($r['storage']['disk_free_bytes']) }} · logs {{ $bytes($r['storage']['logs_size_bytes']) }}</span>
                        </td>
                        <td>{{ $app['response_ms'] !== null ? $app['response_ms'].' ms' : '—' }}</td>
                        <td>
                            <details>
                                <summary>Details</summary>
                                <pre>{{ json_encode(\Illuminate\Support\Arr::except($r, ['runtime.extensions']) + ['extensions' => implode(', ', $r['runtime']['extensions'] ?? [])], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        </td>
                    @else
                        <td colspan="9" class="muted">No data</td>
                        <td>{{ $app['response_ms'] !== null ? $app['response_ms'].' ms' : '—' }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="12" class="empty">No application monitored yet. <a href="{{ route('apps.index') }}">Add one</a> or set <code>STATUS_APPS</code> in the environment.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="legend">↓ behind the most recent version across all applications. Auto-refresh every 60 s, data cached for {{ config('status.cache_ttl') }} s.</p>
@endsection
