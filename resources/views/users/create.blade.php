@extends('layouts.app')
@section('title', 'Create staff account')

@section('content')
<div class="container" style="max-width:760px;padding-top:30px;padding-bottom:40px">
    <h1>Create staff account</h1>
    <p class="text-muted">Accounts are private to the DTC portal; public registration is not available.</p>

    <div class="panel panel-default">
        <div class="panel-body">
            <form method="POST" action="{{ route('users.store') }}">
                @csrf
                <div class="form-group">
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" class="form-control" value="{{ old('name') }}" maxlength="255" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" maxlength="255" required>
                </div>
                <div class="form-group">
                    <label for="role">Role</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="staff" @selected(old('role', 'staff') === 'staff')>Staff — process claims and view registrants</option>
                        <option value="admin" @selected(old('role') === 'admin')>Admin — full portal and account access</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="password">Initial password</label>
                    <input id="password" name="password" type="password" class="form-control" minlength="12" autocomplete="new-password" required>
                    <p class="help-block">Use at least 12 characters. Share the initial password privately; the user can change it after signing in.</p>
                </div>
                <div class="form-group">
                    <label for="password_confirmation">Confirm initial password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" minlength="12" autocomplete="new-password" required>
                </div>
                <button type="submit" class="btn btn-action btn-action--primary">Create account</button>
                <a href="{{ route('users.index') }}" class="btn btn-action">Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection
