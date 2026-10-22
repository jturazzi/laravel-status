@extends('layouts.app')

@section('title', 'Applications')

@section('content')
    <section class="panel">
        <h2>Add an application</h2>
        <form class="grid" method="POST" action="{{ route('apps.store') }}">
            @csrf
            <div>
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="100">
                @error('name')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="url">Base URL</label>
                <input type="url" id="url" name="url" value="{{ old('url') }}" placeholder="https://app.example.com" required>
                @error('url')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="token">Token <span class="muted">(optional)</span></label>
                <input type="password" id="token" name="token" autocomplete="off" placeholder="Shared STATUS_TOKEN if empty">
                @error('token')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div><button type="submit">Add</button></div>
        </form>
        <p class="hint">The application must expose <code>{{ config('status.path') }}</code> (package <code>jturazzi/laravel-system-info</code>). Tokens are stored encrypted.</p>
    </section>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>URL</th>
                <th>Token</th>
                <th>Source</th>
                <th>Enabled</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($envApps as $app)
                <tr>
                    <td><b>{{ $app['name'] }}</b></td>
                    <td>{{ $app['url'] }}</td>
                    <td>{{ $app['token'] ? 'Own token' : 'Shared' }}</td>
                    <td><span class="badge unreachable">.env</span></td>
                    <td>Yes</td>
                    <td class="muted">Edit <code>STATUS_APPS</code></td>
                </tr>
            @endforeach
            @foreach ($apps as $app)
                <tr>
                    <td><b>{{ $app->name }}</b></td>
                    <td>{{ $app->url }}</td>
                    <td>{{ $app->token ? 'Own token' : 'Shared' }}</td>
                    <td><span class="badge ok">Database</span></td>
                    <td>{{ $app->enabled ? 'Yes' : 'No' }}</td>
                    <td>
                        <div class="actions">
                            <a href="{{ route('apps.edit', $app) }}">Edit</a>
                            <form method="POST" action="{{ route('apps.destroy', $app) }}" onsubmit="return confirm('Remove {{ addslashes($app->name) }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="danger">Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            @if ($envApps->isEmpty() && $apps->isEmpty())
                <tr><td colspan="6" class="empty">No application yet.</td></tr>
            @endif
            </tbody>
        </table>
    </div>
@endsection
