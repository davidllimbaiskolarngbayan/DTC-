@extends('layouts.app')
@section('title', ! empty($isOwnProfile) ? 'Edit my profile' : 'Edit account')

@section('content')
<div class="container" style="max-width:760px;padding-top:30px;padding-bottom:40px">
    <div class="clearfix page-header" style="margin-top:0">
        <div class="pull-left">
            <h1>{{ ! empty($isOwnProfile) ? 'Edit my profile' : 'Edit account' }}</h1>
            <p class="text-muted">Update the account profile. Leave password blank to keep the current password.</p>
        </div>
        <a href="{{ ! empty($isOwnProfile) ? route('profile.show') : route('users.show', $user) }}" class="btn btn-action pull-right">View profile</a>
    </div>

    <div class="panel panel-default">
        <div class="panel-body">
            <form method="POST" action="{{ ! empty($isOwnProfile) ? route('profile.update') : route('users.update', $user) }}">
                @csrf
                @method('PUT')
                <div class="form-group">
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" class="form-control" value="{{ old('name', $user->name) }}" maxlength="255" required>
                    @error('name')<p class="help-block text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" class="form-control" value="{{ old('email', $user->email) }}" maxlength="255" required>
                    @error('email')<p class="help-block text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label for="password">New password <span class="text-muted">(optional)</span></label>
                    <input id="password" name="password" type="password" class="form-control" minlength="12" autocomplete="new-password">
                    <p class="help-block">If changed, use at least 12 characters.</p>
                    @error('password')<p class="help-block text-danger">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" minlength="12" autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-action btn-action--primary">Save changes</button>
                <a href="{{ ! empty($isOwnProfile) ? route('profile.show') : route('users.show', $user) }}" class="btn btn-action">Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection
