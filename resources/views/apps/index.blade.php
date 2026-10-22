@extends('layouts.app')

@section('title', __('Applications'))

@section('content')
    <h1>{{ __('Applications') }}</h1>
    <p class="lead">{!! __('Each application needs the :package package and a :token. It is checked as soon as you add or save it.', ['package' => '<code>jturazzi/laravel-system-info</code>', 'token' => '<code>SYSTEM_INFO_TOKEN</code>']) !!}</p>

    <section class="panel">
        <h2>{{ __('Add an application') }}</h2>
        <form class="fields" method="POST" action="{{ route('apps.store') }}">
            @csrf
            <div>
                <label for="name">{{ __('Name') }}</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="100">
                @error('name')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="url">{{ __('Address') }}</label>
                <input type="url" id="url" name="url" value="{{ old('url') }}" placeholder="https://app.example.com" required>
                @error('url')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="token">{{ __('Token') }} <span class="opt">{{ __('(optional)') }}</span></label>
                <input type="password" id="token" name="token" autocomplete="off" placeholder="{{ __('Uses STATUS_TOKEN when empty') }}">
                @error('token')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div><button type="submit">{{ __('Add and check') }}</button></div>
        </form>
        <p class="hint">{!! __('Tokens are stored encrypted. The token must match :variable in the application.', ['variable' => '<code>SYSTEM_INFO_TOKEN</code>']) !!}</p>
    </section>

    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>{{ __('Name') }}</th>
                <th>{{ __('Address') }}</th>
                <th>{{ __('Token') }}</th>
                <th>{{ __('Managed in') }}</th>
                <th>{{ __('Monitoring') }}</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($envApps as $key => $app)
                <tr>
                    <td><b>{{ $app['name'] }}</b></td>
                    <td>{{ $app['url'] }}</td>
                    <td>{{ $app['token'] ? __('Own token') : __('Shared token') }}</td>
                    <td class="muted" title="{{ __('Edit STATUS_APPS in the environment to change it') }}">{{ __('Environment') }}</td>
                    <td>{{ __('Active') }}</td>
                    <td>
                        <div class="actions">
                            <form method="POST" action="{{ route('apps.check', $key) }}">@csrf<button type="submit" class="link">{{ __('Check') }}</button></form>
                        </div>
                    </td>
                </tr>
            @endforeach
            @foreach ($apps as $app)
                <tr>
                    <td><b>{{ $app->name }}</b></td>
                    <td>{{ $app->url }}</td>
                    <td>{{ $app->token ? __('Own token') : __('Shared token') }}</td>
                    <td>{{ __('This page') }}</td>
                    <td @class(['muted' => ! $app->enabled])>{{ $app->enabled ? __('Active') : __('Paused') }}</td>
                    <td>
                        <div class="actions">
                            <form method="POST" action="{{ route('apps.check', 'db-'.$app->id) }}">@csrf<button type="submit" class="link">{{ __('Check') }}</button></form>
                            <a href="{{ route('apps.edit', $app) }}">{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('apps.destroy', $app) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from(__('Stop monitoring :name?', ['name' => $app->name])) }})">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="link danger">{{ __('Remove') }}</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            @if ($envApps->isEmpty() && $apps->isEmpty())
                <tr><td colspan="6" class="empty">{{ __('No application yet. Add the first one above.') }}</td></tr>
            @endif
            </tbody>
        </table>
    </div>
@endsection
