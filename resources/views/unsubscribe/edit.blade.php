@extends('layouts.app')
@section('title', 'Unsubscribe')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-8 col-md-offset-2">
            <div class="panel panel-default">
                <div class="panel-heading">Unsubscribe</div>
                <div class="panel-body">
                    @if (! $subscribed)
                        <p>You are already unsubscribed.</p>
                    @else
                        <p>Do you want to stop receiving marketing emails from us?</p>
                        <form method="POST" action="{{ route('dtc.unsubscribe.update', $encUserId) }}">
                            @csrf @method('PUT')
                            <button type="submit" class="btn btn-action btn-action--primary">Confirm unsubscribe</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
