@extends('layouts.app')
@section('title', 'Claims report')

@section('content')
<main class="container" style="padding-top:30px;padding-bottom:48px">
    <header class="clearfix" style="margin-bottom:22px">
        <div class="pull-left">
            <h1 style="margin-top:0">Claims report</h1>
            <p class="text-muted">Claim volumes and current status for the selected submission period.</p>
        </div>
        <a href="{{ route('claims.index') }}" class="btn btn-action pull-right">Back to claims</a>
    </header>

    <form method="GET" action="{{ route('claims.report') }}" class="panel panel-default">
        <div class="panel-body">
            <div class="row">
                <div class="col-sm-4 form-group">
                    <label for="report-from">Submitted from</label>
                    <input id="report-from" type="date" name="date_from" class="form-control" value="{{ $dateFrom }}" max="{{ now(config('dtc.timezone'))->toDateString() }}">
                </div>
                <div class="col-sm-4 form-group">
                    <label for="report-to">Submitted to</label>
                    <input id="report-to" type="date" name="date_to" class="form-control" value="{{ $dateTo }}" max="{{ now(config('dtc.timezone'))->toDateString() }}">
                </div>
                <div class="col-sm-4 form-group">
                    <button type="submit" class="btn btn-action btn-action--primary btn-block" style="margin-top:25px">Update report</button>
                </div>
            </div>
        </div>
    </form>

    <div class="row">
        @foreach ([
            ['Total claims', $totalClaims],
            ['Open claims', $openClaims],
            ['Closed claims', $closedClaims],
        ] as [$label, $value])
            <div class="col-sm-4">
                <div class="panel panel-default"><div class="panel-body">
                    <strong style="font-size:24px">{{ number_format($value) }}</strong>
                    <span class="text-muted"> {{ $label }}</span>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-md-5">
            <section class="panel panel-default">
                <div class="panel-heading"><strong>Current status breakdown</strong></div>
                <div class="table-responsive">
                    <table class="table table-striped" style="margin-bottom:0">
                        <thead><tr><th>Status</th><th class="text-right">Claims</th></tr></thead>
                        <tbody>
                            @foreach ($statuses as $status => $label)
                                <tr>
                                    <td>{{ $label }}</td>
                                    <td class="text-right">{{ number_format($statusCounts[$status] ?? 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <div class="col-md-7">
            <section class="panel panel-default">
                <div class="panel-heading"><strong>Claims submitted by month</strong></div>
                <div class="table-responsive">
                    <table class="table table-striped" style="margin-bottom:0">
                        <thead><tr><th>Month</th><th class="text-right">Submitted</th></tr></thead>
                        <tbody>
                            @forelse ($monthlySubmitted as $month => $count)
                                <tr><td>{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $month)->format('F Y') }}</td><td class="text-right">{{ number_format($count) }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted">No claims in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>

    @if (Auth::user()->isAdmin())
        <p class="text-muted">The detailed claim export includes customer descriptions and staff notes. Keep downloaded reports secure.</p>
        <a href="{{ route('claims.export', ['date_from' => $dateFrom, 'date_to' => $dateTo]) }}" class="btn btn-action btn-action--primary"><span class="fa fa-download" aria-hidden="true"></span> &nbsp;Download detailed claims</a>
    @endif
</main>
@endsection
