@extends('layouts.app')
@section('title', 'Change Password')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">Change Password</div>
                <div class="panel-body">
                    <form class="form-horizontal" role="form" method="POST" action="{{ route('password.update') }}">
                        @csrf

                        <div class="form-group">
                            <label for="current_password" class="col-md-4 control-label">Current Password</label>
                            <div class="col-md-6">
                                <input id="current_password" type="password" class="form-control" name="current_password" autocomplete="current-password" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password" class="col-md-4 control-label">New Password</label>
                            <div class="col-md-6">
                                <input id="password" type="password" class="form-control" name="password" autocomplete="new-password" minlength="12" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password_confirmation" class="col-md-4 control-label">Confirm New Password</label>
                            <div class="col-md-6">
                                <input id="password_confirmation" type="password" class="form-control" name="password_confirmation" autocomplete="new-password" minlength="12" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <div class="col-md-6 col-md-offset-4">
                                <button type="submit" class="btn btn-action btn-action--primary">Change Password</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
