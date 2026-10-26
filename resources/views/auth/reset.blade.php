@extends('layouts.app')
@section('title', 'Reset Password')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">Reset Password</div>
                <div class="panel-body">
                    <form class="form-horizontal" role="form" method="POST" action="{{ route('password.store') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">

                        <div class="form-group">
                            <label for="email" class="col-md-4 control-label">E-Mail Address</label>
                            <div class="col-md-6">
                                <input id="email" type="email" class="form-control" name="email" value="{{ old('email', $email) }}" autocomplete="username" required>
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
                                <button type="submit" class="btn btn-action btn-action--primary">Reset Password</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
