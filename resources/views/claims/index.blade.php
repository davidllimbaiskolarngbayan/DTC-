@extends('layouts.app')
@section('title', 'Claims')

@section('content')
<div class="container">
    <div class="clearfix" style="margin:30px 0 20px">
        <div class="pull-left">
            <h1 style="margin-top:0">Claims</h1>
            <p class="text-muted">Review and track warranty claims.</p>
        </div>
        <div class="pull-right">
            <a href="{{ route('claims.report') }}" class="btn btn-action">View reports</a>
            @if (Auth::user()->isAdmin())
                <a href="{{ route('claims.export', request()->query()) }}" class="btn btn-action btn-action--primary"><span class="fa fa-download" aria-hidden="true"></span> &nbsp;Download Claims</a>
            @endif
            <a href="{{ route('dtc.lists') }}" class="btn btn-action">Find a registrant</a>
        </div>
    </div>

    <div class="row" style="margin-bottom:15px">
        <div class="col-sm-4">
            <div class="panel panel-default"><div class="panel-body">
                <strong style="font-size:24px">{{ number_format($submittedClaimsCount) }}</strong>
                <span class="text-muted"> Submitted — awaiting review</span>
            </div></div>
        </div>
        <div class="col-sm-4">
            <div class="panel panel-default"><div class="panel-body">
                <strong style="font-size:24px">{{ number_format($activeClaimsCount) }}</strong>
                <span class="text-muted"> Active claims</span>
            </div></div>
        </div>
        <div class="col-sm-4">
            <div class="panel panel-default"><div class="panel-body">
                <strong style="font-size:24px">{{ number_format($totalClaimsCount) }}</strong>
                <span class="text-muted"> Total claims</span>
            </div></div>
        </div>
    </div>

    <ul class="nav nav-pills" style="margin-bottom:18px">
        <li class="{{ $selectedQuickView === '' ? 'active' : '' }}"><a href="{{ route('claims.index') }}">All</a></li>
        <li class="{{ $selectedQuickView === 'submitted' ? 'active' : '' }}"><a href="{{ route('claims.index', ['quick_view' => 'submitted']) }}">New</a></li>
        <li class="{{ $selectedQuickView === 'under_review' ? 'active' : '' }}"><a href="{{ route('claims.index', ['quick_view' => 'under_review']) }}">Under review</a></li>
        <li class="{{ $selectedQuickView === 'need_more_info' ? 'active' : '' }}"><a href="{{ route('claims.index', ['quick_view' => 'need_more_info']) }}">Waiting for customer</a></li>
        <li class="{{ $selectedQuickView === 'follow_up' ? 'active' : '' }}"><a href="{{ route('claims.index', ['quick_view' => 'follow_up']) }}">Needs follow-up <span class="badge">{{ $followUpClaimsCount }}</span></a></li>
        <li class="{{ $selectedQuickView === 'overdue' ? 'active' : '' }}"><a href="{{ route('claims.index', ['quick_view' => 'overdue']) }}">Overdue response <span class="badge">{{ $overdueClaimsCount }}</span></a></li>
        <li class="{{ $selectedQuickView === 'notification_failed' ? 'active' : '' }}"><a href="{{ route('claims.index', ['quick_view' => 'notification_failed']) }}">Email failures <span class="badge">{{ $failedNotificationCount }}</span></a></li>
        <li class="{{ $selectedQuickView === 'approved' ? 'active' : '' }}"><a href="{{ route('claims.index', ['quick_view' => 'approved']) }}">Approved</a></li>
        <li class="{{ $selectedQuickView === 'rejected' ? 'active' : '' }}"><a href="{{ route('claims.index', ['quick_view' => 'rejected']) }}">Rejected</a></li>
    </ul>

    <form method="GET" action="{{ route('claims.index') }}" class="panel panel-default">
        <div class="panel-body">
            @if ($selectedQuickView)
                <input type="hidden" name="quick_view" value="{{ $selectedQuickView }}">
            @endif
            <div class="row">
                <div class="col-sm-4 form-group">
                    <label for="claim-search">Claim No. or DTC Code</label>
                    <input id="claim-search" type="search" name="search" class="form-control" value="{{ request('search') }}" placeholder="e.g. CLM-000001">
                </div>
                <div class="col-sm-2 form-group">
                    <label for="claim-status">Status</label>
                    <select id="claim-status" name="status" class="form-control">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-2 form-group">
                    <label for="date-from">Submitted from</label>
                    <input id="date-from" type="date" name="date_from" class="form-control" value="{{ request('date_from') }}" max="{{ now(config('dtc.timezone'))->toDateString() }}">
                </div>
                <div class="col-sm-2 form-group">
                    <label for="date-to">Submitted to</label>
                    <input id="date-to" type="date" name="date_to" class="form-control" value="{{ request('date_to') }}" max="{{ now(config('dtc.timezone'))->toDateString() }}">
                </div>
                <div class="col-sm-2 form-group">
                    <label class="sr-only" for="claims-filter">Filter</label>
                    <button id="claims-filter" type="submit" class="btn btn-action btn-action--primary btn-block" style="margin-top:25px">Filter</button>
                </div>
            </div>
        </div>
    </form>
    <p class="text-muted">The report uses the filters above and includes claim descriptions and staff notes. Keep downloaded reports secure.</p>
    <p class="text-muted">Submitted claims are marked overdue after 3 days without a response. Open claims without activity for 3 days are also marked for follow-up.</p>

    <div class="panel panel-default">
        <div class="table-responsive">
            <table class="table table-striped" style="margin-bottom:0">
                <thead>
                    <tr><th>Claim No.</th><th>Registrant</th><th>DTC Code</th><th>Subject</th><th>Submitted</th><th>Status</th><th>Assigned to</th><th>Response</th><th>Follow-up</th></tr>
                </thead>
                <tbody>
                @forelse ($claims as $claim)
                    <tr>
                        <td><a href="{{ route('claims.show', $claim) }}"><strong>{{ $claim->claim_number }}</strong></a></td>
                        <td>{{ $claim->registrant->firstname }} {{ $claim->registrant->lastname }}</td>
                        <td>{{ $claim->registrant->dtc_card_no }}</td>
                        <td>{{ $claim->subject }}</td>
                        <td>{{ $claim->created_at->format('Y-m-d') }}</td>
                        <td><span class="label label-default">{{ $statuses[$claim->status] ?? $claim->status }}</span></td>
                        <td>{{ $claim->assignedStaff?->name ?? 'Unassigned' }}</td>
                        <td>
                            @if ($claim->status === 'submitted' && $claim->created_at->toDateTimeString() <= $overdueCutoff)
                                <span class="label label-danger">Overdue</span>
                            @else
                                <span class="text-muted">{{ $claim->status === 'submitted' ? 'Within 3 days' : '—' }}</span>
                            @endif
                        </td>
                        <td>
                            @if (\App\Models\Claim::isOpenStatus($claim->status) && $claim->updated_at->toDateTimeString() <= $followUpCutoff)
                                <span class="label label-warning">Needs follow-up</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-muted" style="padding:35px">No claims found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="text-center">{{ $claims->links() }}</div>
</div>
@endsection
