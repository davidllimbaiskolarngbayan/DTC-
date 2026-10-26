@extends('layouts.app')
@section('title', 'Staff accounts')

@section('content')
<div class="container" style="padding-top:30px;padding-bottom:40px">
    <div class="clearfix page-header" style="margin-top:0">
        <div class="pull-left">
            <h1>Staff accounts</h1>
            <p class="text-muted">Create admin and staff accounts for the DTC portal.</p>
        </div>
        <a href="{{ route('users.create') }}" class="btn btn-action btn-action--primary pull-right">Create account</a>
    </div>

    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td><span class="label {{ $user->role === 'admin' ? 'label-primary' : 'label-default' }}">{{ ucfirst($user->role) }}</span></td>
                        <td>{{ $user->created_at?->format('Y-m-d') }}</td>
                        <td>
                            <a href="{{ route('users.show', $user) }}" class="btn btn-action">View profile</a>
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-action btn-action--primary">Edit</a>
                            @if ($user->role === 'staff')
                                <form method="POST" action="{{ route('users.destroy', $user) }}" style="display:inline" onsubmit="return confirm('Soft-delete this staff account? The account will no longer be able to sign in.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-action btn-action--danger">Delete</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $users->links() }}
</div>
@endsection
