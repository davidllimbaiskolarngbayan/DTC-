@extends('layouts.app')
@section('title', ! empty($isOwnProfile) ? 'My profile' : 'Account profile')

@section('content')
<div class="container" style="max-width:900px;padding-top:30px;padding-bottom:40px">
    <div class="clearfix page-header" style="margin-top:0">
        <div class="pull-left">
            <h1>{{ ! empty($isOwnProfile) ? 'My profile' : 'Account profile' }}</h1>
            <p class="text-muted">{{ ! empty($isOwnProfile) ? 'View your account details.' : 'View staff account details.' }}</p>
        </div>
        <a href="{{ ! empty($isOwnProfile) ? route('profile.edit') : route('users.edit', $user) }}" class="btn btn-action btn-action--primary pull-right">Edit profile</a>
    </div>

    <div class="panel panel-default">
        <table class="table table-striped" style="margin-bottom:0">
            <tbody>
                <tr><td style="width:25%">Name</td><td>{{ $user->name }}</td></tr>
                <tr><td>Email</td><td>{{ $user->email }}</td></tr>
                <tr><td>Role</td><td>{{ ucfirst($user->role) }}</td></tr>
                <tr><td>Account created</td><td>{{ $user->created_at?->format('Y-m-d H:i') }}</td></tr>
                <tr><td>Email verified</td><td>{{ $user->email_verified_at?->format('Y-m-d H:i') ?? 'Not verified' }}</td></tr>
            </tbody>
        </table>
    </div>

    <a href="{{ ! empty($isOwnProfile) ? route('dashboard') : route('users.index') }}" class="btn btn-action">{{ ! empty($isOwnProfile) ? 'Back to dashboard' : 'Back to accounts' }}</a>
</div>
@endsection
