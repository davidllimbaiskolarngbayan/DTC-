@extends('layouts.app')
@section('title', 'Staff alert recipients')

@section('content')
<div class="container" style="padding-top:30px;padding-bottom:40px">
    <div class="page-header" style="margin-top:0">
        <h1>Staff alert recipients</h1>
        <p class="text-muted">Choose staff accounts that should receive new claim alerts and follow-up reminders. Admin accounts are not included.</p>
    </div>

    @if ($staff->isEmpty())
        <div class="alert alert-warning" role="status">No staff accounts are available to receive claim alerts.</div>
    @else
        <form method="POST" action="{{ route('settings.claim-notifications.update') }}">
            @csrf
            @method('PUT')
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Who should receive staff emails?</strong></div>
                <div class="list-group">
                    @foreach ($staff as $staffUser)
                        <label class="list-group-item" style="display:flex;align-items:center;gap:12px;font-weight:400;margin:0">
                            <input
                                type="checkbox"
                                name="recipients[]"
                                value="{{ $staffUser->id }}"
                                {{ in_array($staffUser->id, $selectedStaffIds) ? 'checked' : '' }}
                            >
                            <span>
                                <strong>{{ $staffUser->name }}</strong><br>
                                <span class="text-muted">{{ $staffUser->email }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="panel-body">
                    <p class="text-muted">Select multiple accounts if more than one staff member should receive each alert. Leaving all unchecked disables staff alerts and reminders.</p>
                    @if ($errors->has('recipients') || $errors->has('recipients.*'))
                        <div class="alert alert-danger" role="alert">{{ $errors->first('recipients') ?: $errors->first('recipients.*') }}</div>
                    @endif
                    <button type="submit" class="btn btn-action btn-action--primary">Save recipients</button>
                </div>
            </div>
        </form>
    @endif
</div>
@endsection
