@php
    $bytes = function (?int $b): string {
        if ($b === null) return '—';
        $u = [__('B'), __('KB'), __('MB'), __('GB'), __('TB')];
        $i = $b > 0 ? min((int) floor(log($b, 1024)), 4) : 0;
        return \Illuminate\Support\Number::format(round($b / 1024 ** $i, 1), maxPrecision: 1, locale: app()->getLocale()).' '.$u[$i];
    };
    $labels = ['ok' => __('Healthy'), 'degraded' => __('Degraded'), 'down' => __('Down'), 'unreachable' => __('Unreachable')];
    // MySQL stops at 9.x, MariaDB starts at 10.x: the version tells them apart.
    $engine = fn (array $db) => match ($db['driver']) {
        'mysql' => (int) ($db['version'] ?? 0) >= 10 ? 'MariaDB' : 'MySQL',
        'mariadb' => 'MariaDB', 'sqlite' => 'SQLite', 'pgsql' => 'PostgreSQL',
        default => $db['driver'],
    };
    $percent = fn (?float $v) => $v === null ? '—' : \Illuminate\Support\Number::percentage($v, maxPrecision: 1, locale: app()->getLocale());
    $isBehind = fn (?string $v, ?string $latest) => $v && $latest && version_compare($v, $latest, '<');
    $order = ['down' => 0, 'unreachable' => 1, 'degraded' => 2, 'ok' => 3];
    $sorted = collect($apps)->sortBy(fn ($a) => [$order[$a['status']] ?? 9, $a['name']]);
    $total = count($apps);
    $attention = $total - ($summary['ok'] ?? 0);
    $checked = \Illuminate\Support\Carbon::parse($checked_at)->setTimezone(config('app.timezone'));
    $yesNo = fn ($v) => $v === null ? '—' : ($v ? __('Yes') : __('No'));
    $date = fn (string $iso, string $format = 'Y-m-d H:i') => \Illuminate\Support\Carbon::parse($iso)->setTimezone(config('app.timezone'))->format(__($format));
    $age = function (string $since): string {
        $minutes = (int) \Illuminate\Support\Carbon::parse($since)->diffInMinutes(now());
        return match (true) {
            $minutes >= 2 * 1440 => \App\Support\IssueText::duration(intdiv($minutes, 1440), 'days'),
            $minutes >= 120 => \App\Support\IssueText::duration(intdiv($minutes, 60), 'hours'),
            default => \App\Support\IssueText::duration($minutes, 'minutes'),
        };
    };
@endphp
@extends('layouts.app')

@section('title', ($attention > 0 ? "({$attention}) " : '').__('Dashboard'))

@push('head')
    <style>
        .verdict { font-size: clamp(26px, 3.4vw, 36px); line-height: 1.15; letter-spacing: -.02em; margin: 0 0 6px; font-weight: 650; max-width: 42ch; }
        .verdict.is-ok { color: var(--ink); }
        .verdict:not(.is-ok) { color: var(--status); }
        .checked { color: var(--muted); margin: 0 0 22px; }

        .strip { list-style: none; display: flex; gap: 4px; margin: 0 0 14px; padding: 0; }
        .strip li { flex: 1 1 0; min-width: 0; }
        .strip a { display: block; padding: 10px 10px 8px; border-top: 6px solid var(--status); background: var(--panel); border-radius: 0 0 6px 6px; color: var(--ink); text-decoration: none; font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .strip a:hover { background: var(--hover); }
        .strip a small { display: block; color: var(--status); font-weight: 600; }
        .strip li.is-ok a small { color: var(--muted); font-weight: 400; }

        .drift { margin: 0 0 26px; color: var(--muted); }
        .drift strong { color: var(--ink); font-weight: 600; }

        .toolbar { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between; margin-bottom: 12px; }
        .filters { display: inline-flex; flex-wrap: wrap; background: var(--panel); border: 1px solid var(--line); border-radius: 8px; padding: 3px; }
        .filters button { background: transparent; color: var(--muted); border: 0; padding: 5px 12px; border-radius: 6px; font-weight: 600; }
        .filters button[aria-pressed="true"] { background: var(--bg); color: var(--ink); }
        .toolbar input[type=search] { width: min(280px, 100%); background: var(--panel); }

        .board { background: var(--panel); border: 1px solid var(--line); border-radius: 10px; overflow: hidden; }
        .row { display: grid; grid-template-columns: minmax(150px, 1.4fr) minmax(170px, 1.7fr) minmax(100px, .9fr) minmax(120px, 1fr) minmax(140px, 1.2fr) minmax(80px, .7fr) minmax(80px, .7fr) 64px 72px; gap: 14px; align-items: start; padding: 12px 16px 12px 14px; }
        .head { color: var(--muted); font-size: 13px; font-weight: 600; border-bottom: 1px solid var(--line); padding-top: 10px; padding-bottom: 10px; padding-left: 18px; }
        .app { border-bottom: 1px solid var(--line); border-left: 4px solid var(--status); }
        .app:last-of-type { border-bottom: 0; }
        .app:target { background: var(--hover); }
        .k { display: none; }
        .name { font-weight: 650; color: var(--ink); text-decoration: none; }
        .name:hover { text-decoration: underline; }
        .sub { display: block; color: var(--muted); font-size: 13px; overflow-wrap: anywhere; }
        .issues { margin: 6px 0 0; padding: 0; list-style: none; font-size: 13px; color: var(--status); }
        .issues li + li { margin-top: 2px; }
        .behind { color: var(--warn); }
        .nowrap { white-space: nowrap; }
        .row > :first-child .sub { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .bad { color: var(--down); font-weight: 600; }
        .no-data { grid-column: 3 / span 5; color: var(--muted); }
        .toggle { justify-self: end; background: transparent; color: var(--accent); border: 1px solid var(--line); padding: 3px 10px; font-size: 13px; }
        .toggle[aria-expanded="true"] { background: var(--bg); }

        .details { padding: 0 20px 20px 16px; display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 4px 36px; }
        .details h3 { font-size: 14px; margin: 12px 0 6px; }
        .details dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 3px 14px; font-size: 14px; }
        .details dt { color: var(--muted); }
        .details dd { margin: 0; overflow-wrap: anywhere; }
        .details .wide { grid-column: 1 / -1; }
        .chips { display: flex; flex-wrap: wrap; gap: 4px; margin: 0; padding: 0; list-style: none; }
        .chips li { font-size: 12px; padding: 1px 7px; border-radius: 4px; background: var(--bg); color: var(--muted); }
        .raw summary { cursor: pointer; color: var(--accent); font-size: 13px; font-weight: 600; margin-top: 10px; }
        .raw pre { max-height: 320px; overflow: auto; background: var(--bg); padding: 10px; border-radius: 6px; font-size: 12px; }
        .board .empty a { font-weight: 600; }

        @media (max-width: 1080px) {
            .strip { flex-wrap: wrap; }
            .strip li { flex: 1 1 140px; }
            .head { display: none; }
            .row { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px 16px; }
            .row > :first-child, .row > :nth-child(2) { grid-column: 1 / -1; }
            .k { display: block; color: var(--muted); font-size: 12px; }
            .no-data { grid-column: 1 / -1; }
            .toggle { grid-column: 1 / -1; justify-self: start; }
        }
        @media (max-width: 560px) {
            .verdict { font-size: 24px; }
            .strip li { flex: 1 1 calc(50% - 4px); }
            .toolbar { flex-direction: column; align-items: stretch; }
            .filters { display: flex; }
            .filters button { flex: 1 1 auto; padding: 8px 10px; font-size: 13px; }
            .toolbar input[type=search] { width: 100%; }
            .row { padding: 12px; gap: 10px 12px; }
            .toggle { padding: 8px 14px; }
            .details { padding: 0 14px 16px 12px; grid-template-columns: 1fr; }
            .details dl { grid-template-columns: minmax(0, auto) minmax(0, 1fr); }
        }
    </style>
@endpush

@section('content')
@php
    $worst = $sorted->first()['status'] ?? 'ok';
    $verdict = match (true) {
        $total === 0 => __('No applications yet'),
        $attention === 0 => $total === 1 ? __('Your application is healthy') : __('All :total applications are healthy', ['total' => $total]),
        $total === 1 => __('Your application needs attention'),
        $attention === 1 => __(':attention of :total applications needs attention', ['attention' => $attention, 'total' => $total]),
        default => __(':attention of :total applications need attention', ['attention' => $attention, 'total' => $total]),
    };
@endphp
<div id="dashboard">
    <h1 class="verdict is-{{ $attention === 0 ? 'ok' : $worst }}">{{ $verdict }}</h1>
    <p class="checked">
        {{ __('Checked') }} <time datetime="{{ $checked->toIso8601String() }}" title="{{ $checked->format(__('Y-m-d H:i:s')) }}">{{ abs($checked->diffInSeconds()) < 5 ? __('just now') : $checked->diffForHumans() }}</time>,
        <span data-refresh-note>{{ __('updates every minute') }}</span>.
        <a href="?refresh=1">{{ __('Check now') }}</a>
    </p>

    @if ($total > 0)
        <ol class="strip" aria-label="{{ __('Applications by status') }}">
            @foreach ($sorted as $key => $app)
                <li class="is-{{ $app['status'] }}">
                    <a href="#app-{{ $key }}" data-open="{{ $key }}" title="{{ $app['name'] }}: {{ $labels[$app['status']] }}">
                        {{ $app['name'] }}
                        <small>{{ $labels[$app['status']] }}</small>
                    </a>
                </li>
            @endforeach
        </ol>

        <p class="drift">
            @php($strong = fn (string $k) => '<strong>'.e(['laravel' => 'Laravel', 'php' => 'PHP'][$k].' '.$latest[$k]).'</strong>')
            @if ($latest['laravel'] && $latest['php'] && $behind['laravel'] === 0 && $behind['php'] === 0)
                {!! __('Every application runs :laravel and :php.', ['laravel' => $strong('laravel'), 'php' => $strong('php')]) !!}
            @else
            @foreach (['laravel' => 'Laravel', 'php' => 'PHP'] as $k => $label)
                @continue(! $latest[$k])
                @if ($behind[$k] === 0)
                    {!! __('Every application runs :version.', ['version' => $strong($k)]) !!}
                @else
                    @if (($latest_source[$k] ?? 'apps') === 'upstream')
                        {!! __($behind[$k] === 1 ? ':version is the latest release; :count application is behind.' : ':version is the latest release; :count applications are behind.', ['version' => $strong($k), 'count' => $behind[$k]]) !!}
                    @else
                        {!! __($behind[$k] === 1 ? ':version is the newest; :count application is behind.' : ':version is the newest; :count applications are behind.', ['version' => $strong($k), 'count' => $behind[$k]]) !!}
                    @endif
                @endif
            @endforeach
            @endif
        </p>

        <div class="toolbar">
            <div class="filters" role="group" aria-label="{{ __('Show') }}">
                <button type="button" data-filter="all" aria-pressed="true">{{ __('All :count', ['count' => $total]) }}</button>
                <button type="button" data-filter="attention" aria-pressed="false">{{ __('Needs attention :count', ['count' => $attention]) }}</button>
                <button type="button" data-filter="ok" aria-pressed="false">{{ __('Healthy :count', ['count' => $summary['ok'] ?? 0]) }}</button>
            </div>
            <input type="search" data-search placeholder="{{ __('Filter by name or URL') }}" aria-label="{{ __('Filter by name or URL') }}">
        </div>
    @endif

    <div class="board">
        @if ($total > 0)
            <div class="row head" aria-hidden="true">
                <span>{{ __('Application') }}</span><span>{{ __('State') }}</span><span>{{ __('Version') }}</span><span>{{ __('Runtime') }}</span><span>{{ __('Database') }}</span><span>{{ __('Queue') }}</span><span>{{ __('Disk free') }}</span><span>{{ __('Response') }}</span><span></span>
            </div>
        @endif

        @forelse ($sorted as $key => $app)
            @php($r = $app['report'])
            <article class="app is-{{ $app['status'] }}" id="app-{{ $key }}" data-key="{{ $key }}" data-status="{{ $app['status'] }}" data-search="{{ \Illuminate\Support\Str::lower($app['name'].' '.$app['url']) }}">
                <div class="row">
                    <div>
                        <a class="name" href="{{ $app['url'] }}" target="_blank" rel="noopener">{{ $app['name'] }}</a>
                        <span class="sub">{{ preg_replace('#^https?://#', '', $app['url']) }}</span>
                    </div>
                    <div>
                        <span class="pill">{{ $labels[$app['status']] }}</span>
                        @if ($app['error'])
                            <ul class="issues"><li>{{ \App\Support\IssueText::error($app) }}</li></ul>
                        @elseif (! empty($r['issues']))
                            <ul class="issues">@foreach ($r['issues'] as $issue)<li>{{ \App\Support\IssueText::translate($issue['message']) }}</li>@endforeach</ul>
                        @endif
                    </div>
                    @if ($r)
                        <div>
                            <span class="k">{{ __('Version') }}</span>
                            @if ($r['app']['version']){{ $r['app']['version'] }}@else<span class="muted">{{ __('No version') }}</span>@endif
                            <span class="sub">
                                @if ($r['app']['commit'])<code title="{{ $r['app']['commit'] }}">{{ \Illuminate\Support\Str::limit($r['app']['commit'], 8, '') }}</code>@else {{ __('No commit info') }} @endif
                            </span>
                        </div>
                        <div>
                            <span class="k">{{ __('Runtime') }}</span>
                            <span @class(['nowrap', 'behind' => $isBehind($r['runtime']['laravel'], $latest['laravel'])]) @if ($isBehind($r['runtime']['laravel'], $latest['laravel'])) title="{{ __('Behind :version', ['version' => $latest['laravel']]) }}" @endif>Laravel {{ $r['runtime']['laravel'] }}</span>
                            <span @class(['sub', 'behind' => $isBehind($r['runtime']['php'], $latest['php'])])>PHP {{ $r['runtime']['php'] }}</span>
                        </div>
                        <div>
                            <span class="k">{{ __('Database') }}</span>
                            @if ($r['database']['ok'])
                                <span class="nowrap">{{ $engine($r['database']) }} {{ $r['database']['version'] ?? '' }}</span>
                                <span @class(['sub', 'behind' => ($r['database']['pending_migrations'] ?? 0) > 0])>{{ $bytes($r['database']['size_bytes'] ?? null) }}@if (($r['database']['pending_migrations'] ?? 0) > 0), {{ __(':count pending migration(s)', ['count' => $r['database']['pending_migrations']]) }}@endif</span>
                            @else
                                <span class="bad">{{ __('Unreachable') }}</span>
                                <span class="sub">{{ $r['database']['error'] ?? '' }}</span>
                            @endif
                        </div>
                        <div>
                            <span class="k">{{ __('Queue') }}</span>
                            @if (isset($r['queue']['error']))
                                <span class="bad">{{ __('Unavailable') }}</span>
                            @else
                                <span class="nowrap">{{ __(':count pending', ['count' => $r['queue']['pending'] ?? '—']) }}</span>
                                @if (! empty($r['queue']['oldest_pending_at']))
                                    @php($waiting = \Illuminate\Support\Carbon::parse($r['queue']['oldest_pending_at']))
                                    <span class="sub behind" title="{{ __('Oldest pending job available since :date', ['date' => $date($r['queue']['oldest_pending_at'])]) }}">{{ __('oldest waits :age', ['age' => $age($r['queue']['oldest_pending_at'])]) }}</span>
                                @endif
                                @if (($r['queue']['failed'] ?? null) !== null)
                                    <span @class(['sub', 'bad' => $r['queue']['failed'] > 0])>{{ __(':count failed', ['count' => $r['queue']['failed']]) }}</span>
                                @endif
                            @endif
                        </div>
                        <div>
                            <span class="k">{{ __('Disk free') }}</span>
                            {{ $percent($r['storage']['disk_free_percent']) }}
                            <span class="sub">{{ $bytes($r['storage']['disk_free_bytes']) }}</span>
                        </div>
                    @else
                        <div class="no-data">{{ __('No report received.') }}</div>
                    @endif
                    <div>
                        <span class="k">{{ __('Response') }}</span>
                        {{ $app['response_ms'] !== null ? $app['response_ms'].' ms' : '—' }}
                    </div>
                    @if ($r)
                        <button type="button" class="toggle" aria-expanded="false" aria-controls="details-{{ $key }}">{{ __('Details') }}</button>
                    @endif
                </div>

                @if ($r)
                    <section class="details" id="details-{{ $key }}" hidden>
                        <div>
                            <h3>{{ __('Application') }}</h3>
                            <dl>
                                <dt>{{ __('Environment') }}</dt><dd @class(['bad' => $r['app']['environment'] === 'production' && $r['app']['debug']])>{{ $r['app']['environment'] }}{{ $r['app']['debug'] ? ', '.__('debug on') : '' }}</dd>
                                <dt>URL</dt><dd>{{ $r['app']['url'] }}</dd>
                                <dt>Commit</dt><dd>@if ($r['app']['commit'])<code>{{ $r['app']['commit'] }}</code>@else — @endif</dd>
                                <dt>{{ __('Built') }}</dt><dd>{{ $r['app']['build_date'] ? $date($r['app']['build_date']) : '—' }}</dd>
                                <dt>{{ __('Timezone') }}</dt><dd>{{ $r['app']['timezone'] }}, {{ $r['app']['locale'] }}</dd>
                                <dt>Maintenance</dt><dd>{{ $yesNo($r['app']['maintenance']) }}</dd>
                            </dl>
                        </div>
                        <div>
                            <h3>{{ __('Runtime') }}</h3>
                            <dl>
                                <dt>{{ __('Server') }}</dt><dd>{{ $r['runtime']['server'] ?? $r['runtime']['sapi'] }}</dd>
                                <dt>OPcache</dt><dd>{{ $yesNo($r['runtime']['opcache']) }}</dd>
                                <dt>{{ __('Memory limit') }}</dt><dd>{{ $r['runtime']['memory_limit'] }}</dd>
                                <dt>{{ __('Uploads') }}</dt><dd>{{ __(':file per file, :request per request', ['file' => $r['runtime']['upload_max_filesize'], 'request' => $r['runtime']['post_max_size']]) }}</dd>
                                <dt>{{ __('Host') }}</dt><dd>{{ $r['runtime']['hostname'] }}, {{ $r['runtime']['os'] }}</dd>
                            </dl>
                        </div>
                        <div>
                            <h3>{{ __('Data') }}</h3>
                            <dl>
                                <dt>{{ __('Database') }}</dt><dd>{{ $r['database']['database'] ?? '—' }}{{ isset($r['database']['latency_ms']) ? ', '.$r['database']['latency_ms'].' ms' : '' }}</dd>
                                <dt>{{ __('Migrations') }}</dt><dd>{{ ($r['database']['pending_migrations'] ?? null) === null ? '—' : ($r['database']['pending_migrations'] ? __(':count pending migration(s)', ['count' => $r['database']['pending_migrations']]) : __('None pending')) }}</dd>
                                <dt>Cache</dt><dd>{{ $r['cache']['store'] }}, {{ $r['cache']['ok'] ? __('working') : __('failing') }}</dd>
                                <dt>{{ __('Queue') }}</dt><dd>{{ $r['queue']['connection'] }}{{ isset($r['queue']['error']) ? ', '.$r['queue']['error'] : '' }}{{ ! empty($r['queue']['oldest_pending_at']) ? ', '.__('oldest job waiting since :date', ['date' => $date($r['queue']['oldest_pending_at'])]) : '' }}</dd>
                                <dt>{{ __('Storage') }}</dt><dd>{{ $r['storage']['writable'] ? __('Writable') : __('Not writable') }}, {{ __(':free free of :total', ['free' => $bytes($r['storage']['disk_free_bytes']), 'total' => $bytes($r['storage']['disk_total_bytes'])]) }}</dd>
                                <dt>{{ __('Logs') }}</dt><dd>{{ $bytes($r['storage']['logs_size_bytes']) }}</dd>
                            </dl>
                        </div>
                        @if (! empty($r['packages']))
                            <div>
                                <h3>{{ __('Packages') }}</h3>
                                <dl>
                                    @foreach ($r['packages'] as $package => $version)
                                        <dt>{{ $package }}</dt><dd>{{ $version }}</dd>
                                    @endforeach
                                </dl>
                            </div>
                        @endif
                        <div class="wide">
                            <h3>{{ __('PHP extensions (:count)', ['count' => count($r['runtime']['extensions'] ?? [])]) }}</h3>
                            <ul class="chips">@foreach ($r['runtime']['extensions'] ?? [] as $ext)<li>{{ $ext }}</li>@endforeach</ul>
                            <details class="raw">
                                <summary>{{ __('Raw report') }}</summary>
                                <pre>{{ json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                            </details>
                        </div>
                    </section>
                @endif
            </article>
        @empty
            <p class="empty">{{ __('Nothing to monitor yet.') }} <a href="{{ route('apps.index') }}">{{ __('Add an application') }}</a> {!! __('or list them in :variable.', ['variable' => '<code>STATUS_APPS</code>']) !!}</p>
        @endforelse
        <p class="empty" data-no-match hidden>{{ __('No application matches this filter.') }}</p>
    </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
    const state = { filter: 'all', search: '', open: new Set() };
    const t = {{ \Illuminate\Support\Js::from(['details' => __('Details'), 'hide' => __('Hide'), 'failed' => __('the last update failed, retrying in a minute')]) }};
    const root = () => document.getElementById('dashboard');

    function apply() {
        let shown = 0;
        root().querySelectorAll('.app').forEach((app) => {
            const status = app.dataset.status;
            const matchesFilter = state.filter === 'all' || (state.filter === 'ok' ? status === 'ok' : status !== 'ok');
            const visible = matchesFilter && app.dataset.search.includes(state.search);
            app.hidden = !visible;
            shown += visible ? 1 : 0;

            const open = state.open.has(app.dataset.key);
            const toggle = app.querySelector('.toggle');
            const details = app.querySelector('.details');
            if (toggle && details) {
                toggle.setAttribute('aria-expanded', open);
                toggle.textContent = open ? t.hide : t.details;
                details.hidden = !open;
            }
        });
        root().querySelectorAll('[data-filter]').forEach((b) => b.setAttribute('aria-pressed', b.dataset.filter === state.filter));
        const search = root().querySelector('[data-search]');
        if (search && search.value.trim().toLowerCase() !== state.search) search.value = state.search;
        const none = root().querySelector('[data-no-match]');
        if (none) none.hidden = shown > 0 || !root().querySelector('.app');
    }

    document.addEventListener('click', (e) => {
        const filter = e.target.closest('[data-filter]');
        if (filter) { state.filter = filter.dataset.filter; apply(); return; }

        const toggle = e.target.closest('.toggle');
        if (toggle) {
            const key = toggle.closest('.app').dataset.key;
            state.open.has(key) ? state.open.delete(key) : state.open.add(key);
            apply();
            return;
        }

        const jump = e.target.closest('[data-open]');
        if (jump) { state.filter = 'all'; state.search = ''; state.open.add(jump.dataset.open); apply(); }
    });

    document.addEventListener('input', (e) => {
        if (e.target.matches('[data-search]')) { state.search = e.target.value.trim().toLowerCase(); apply(); }
    });

    // Refresh in the background, keeping filters and open panels.
    async function refresh() {
        try {
            const res = await fetch(location.pathname, { headers: { Accept: 'text/html' }, credentials: 'same-origin' });
            if (!res.ok) throw new Error(res.status);
            const doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            const next = doc.getElementById('dashboard');
            if (!next) throw new Error('missing dashboard');
            root().replaceWith(next);
            document.title = doc.title;
            apply();
        } catch {
            const note = root().querySelector('[data-refresh-note]');
            if (note) note.textContent = t.failed;
        }
    }
    setInterval(refresh, 60000);

    if (location.hash.startsWith('#app-')) state.open.add(location.hash.slice(5));
    apply();
})();
</script>
@endpush
