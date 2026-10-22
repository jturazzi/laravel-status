@extends('layouts.app')

@section('title', 'Applications')

@section('content')
    <h1>Applications</h1>
    <p class="lead">Each application needs the <code>jturazzi/laravel-system-info</code> package and a <code>SYSTEM_INFO_TOKEN</code>. It is checked as soon as you add or save it.</p>

    <section class="panel">
        <h2>Add an application</h2>
        <form class="fields" method="POST" action="{{ route('apps.store') }}">
            @csrf
            <div>
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="100">
                @error('name')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="url">Address</label>
                <input type="url" id="url" name="url" value="{{ old('url') }}" placeholder="https://app.example.com" required>
                @error('url')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="token">Token <span class="opt">(optional)</span></label>
                <input type="password" id="token" name="token" autocomplete="off" placeholder="Uses STATUS_TOKEN when empty">
                @error('token')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div><button type="submit">Add and check</button></div>
        </form>
        <p class="hint">Tokens are stored encrypted. The token must match <code>SYSTEM_INFO_TOKEN</code> in the application.</p>
    </section>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>Address</th>
                <th>Token</th>
                <th>Managed in</th>
                <th>Monitoring</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($envApps as $key => $app)
                <tr>
                    <td><b>{{ $app['name'] }}</b></td>
                    <td>{{ $app['url'] }}</td>
                    <td>{{ $app['token'] ? 'Own token' : 'Shared token' }}</td>
                    <td class="muted" title="Edit STATUS_APPS in the environment to change it">Environment</td>
                    <td>Active</td>
                    <td>
                        <div class="actions">
                            <form method="POST" action="{{ route('apps.check', $key) }}">@csrf<button type="submit" class="link">Check</button></form>
                        </div>
                    </td>
                </tr>
            @endforeach
            @foreach ($apps as $app)
                <tr>
                    <td><b>{{ $app->name }}</b></td>
                    <td>{{ $app->url }}</td>
                    <td>{{ $app->token ? 'Own token' : 'Shared token' }}</td>
                    <td>This page</td>
                    <td @class(['muted' => ! $app->enabled])>{{ $app->enabled ? 'Active' : 'Paused' }}</td>
                    <td>
                        <div class="actions">
                            <form method="POST" action="{{ route('apps.check', 'db-'.$app->id) }}">@csrf<button type="submit" class="link">Check</button></form>
                            <a href="{{ route('apps.edit', $app) }}">Edit</a>
                            <form method="POST" action="{{ route('apps.destroy', $app) }}" onsubmit="return confirm('Stop monitoring {{ addslashes($app->name) }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="link danger">Remove</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            @if ($envApps->isEmpty() && $apps->isEmpty())
                <tr><td colspan="6" class="empty">No application yet. Add the first one above.</td></tr>
            @endif
            </tbody>
        </table>
    </div>
@endsection
