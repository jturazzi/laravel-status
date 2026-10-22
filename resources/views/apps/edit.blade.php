@extends('layouts.app')

@section('title', 'Edit '.$app->name)

@section('content')
    <section class="panel">
        <h2>Edit {{ $app->name }}</h2>
        <form class="grid" method="POST" action="{{ route('apps.update', $app) }}">
            @csrf
            @method('PUT')
            <div>
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $app->name) }}" required maxlength="100">
                @error('name')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="url">Base URL</label>
                <input type="url" id="url" name="url" value="{{ old('url', $app->url) }}" required>
                @error('url')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="token">New token</label>
                <input type="password" id="token" name="token" autocomplete="off" placeholder="{{ $app->token ? 'Leave empty to keep the current token' : 'Shared STATUS_TOKEN if empty' }}">
                @error('token')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <input type="hidden" name="enabled" value="0">
                <label class="inline"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $app->enabled))> Enabled</label>
                @if ($app->token)
                    <input type="hidden" name="clear_token" value="0">
                    <label class="inline"><input type="checkbox" name="clear_token" value="1"> Remove token (use shared)</label>
                @endif
            </div>
            <div class="actions">
                <button type="submit">Save</button>
                <a class="button secondary" href="{{ route('apps.index') }}">Cancel</a>
            </div>
        </form>
    </section>
@endsection
