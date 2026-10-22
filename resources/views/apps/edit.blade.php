@extends('layouts.app')

@section('title', 'Edit '.$app->name)

@section('content')
    <h1>Edit {{ $app->name }}</h1>
    <p class="lead">Saving checks the application again.</p>

    <section class="panel">
        <form class="fields" method="POST" action="{{ route('apps.update', $app) }}">
            @csrf
            @method('PUT')
            <div>
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name', $app->name) }}" required maxlength="100">
                @error('name')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="url">Address</label>
                <input type="url" id="url" name="url" value="{{ old('url', $app->url) }}" required>
                @error('url')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="token">New token <span class="opt">(optional)</span></label>
                <input type="password" id="token" name="token" autocomplete="off" placeholder="{{ $app->token ? 'Keeps the current token when empty' : 'Uses STATUS_TOKEN when empty' }}">
                @error('token')<div class="field-error">{{ $message }}</div>@enderror
            </div>
            <div>
                <input type="hidden" name="enabled" value="0">
                <label class="check"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $app->enabled))> Monitor this application</label>
                @if ($app->token)
                    <input type="hidden" name="clear_token" value="0">
                    <label class="check"><input type="checkbox" name="clear_token" value="1"> Use the shared token instead</label>
                @endif
            </div>
            <div class="actions" style="justify-content: flex-start">
                <button type="submit">Save and check</button>
                <a class="button quiet" href="{{ route('apps.index') }}">Cancel</a>
            </div>
        </form>
    </section>
@endsection
