@extends('layouts.app')
@section('title', 'Create claim')

@section('content')
<div class="container">
    <h1 style="margin:30px 0 20px">Create claim</h1>
    @error('email_notification')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Registrant and purchase</strong></div>
        <div class="panel-body">
            <p><strong>{{ $dtc->firstname }} {{ $dtc->lastname }}</strong> &middot; DTC Code {{ $dtc->dtc_card_no }}</p>
            <p>Registration ID: {{ $dtc->vehicle_reg_id }} &middot; Purchase date: {{ $dtc->date_of_purchase?->format('Y-m-d') ?? 'Not recorded' }}</p>
            <p>Email: {{ $dtc->email }}</p>
            @if ($eligible)
                <p><span class="label label-default">Within warranty</span> {{ $eligibilityReason }}</p>
            @else
                <div class="alert alert-warning" role="status">
                    <strong>Current warranty status:</strong> {{ $eligibilityReason }}
                    <br>Claim eligibility is based on when the issue happened, not when the claim is submitted. Issues reported after warranty expiry can still be submitted for manual review.
                </div>
            @endif
            <p class="help-block">The 12-month warranty period is checked against the issue date. If no issue date is provided, the claim will require manual review.</p>
        </div>
    </div>

    @if ($openClaims->isNotEmpty())
        <div class="panel panel-warning">
            <div class="panel-heading"><strong>Existing open claims</strong></div>
            <div class="table-responsive">
                <table class="table table-striped" style="margin-bottom:0">
                    <thead><tr><th>Claim No.</th><th>Subject</th><th>Status</th><th>Submitted</th></tr></thead>
                    <tbody>
                    @foreach ($openClaims as $claim)
                        <tr>
                            <td><a href="{{ route('claims.show', $claim) }}" target="_blank" rel="noopener">{{ $claim->claim_number }}</a></td>
                            <td>{{ $claim->subject }}</td>
                            <td>{{ \App\Models\Claim::STATUSES[$claim->status] ?? $claim->status }}</td>
                            <td>{{ $claim->created_at->format('Y-m-d') }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="panel-body">
                <p class="text-muted" style="margin-bottom:0">To avoid duplicate claims, submit another claim only for a different issue or incident.</p>
            </div>
        </div>
    @endif
    <div class="panel panel-default">
        <div class="panel-heading"><strong>Claim details</strong></div>
        <div class="panel-body">
            <form method="POST" action="{{ route('claims.store', $dtc) }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label for="subject">Claim subject</label>
                    <input id="subject" type="text" name="subject" class="form-control" maxlength="120" value="{{ old('subject') }}" required>
                </div>
                <div class="form-group">
                    <label for="description">Issue description</label>
                    <textarea id="description" name="description" class="form-control" rows="6" maxlength="5000" required>{{ old('description') }}</textarea>
                </div>
                <div class="form-group">
                    <label for="incident-date">Date the issue happened</label>
                    <input id="incident-date" type="date" name="incident_date" class="form-control" min="{{ $dtc->date_of_purchase?->toDateString() }}" max="{{ now(config('dtc.timezone'))->toDateString() }}" value="{{ old('incident_date') }}">
                    <p class="help-block">The issue date must be on or after the purchase date. An issue that happened during the warranty period may still be eligible even if submitted after expiry.</p>
                </div>
                <div class="form-group">
                    <label for="affected-tyres">Which tyre(s) are affected?</label>
                    <input id="affected-tyres" type="text" name="affected_tyres" class="form-control" maxlength="160" value="{{ old('affected_tyres') }}" placeholder="e.g. front left, rear pair">
                </div>
                <div class="form-group">
                    <label for="claim-attachments">Photo or document (optional)</label>
                    <input id="claim-attachments" type="file" name="attachments[]" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
                    <p class="help-block">One JPG, PNG, WEBP, or PDF file; maximum 5 MB.</p>
                </div>
                @if ($openClaims->isNotEmpty())
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="different_issue_confirmed" value="1" @checked(old('different_issue_confirmed'))>
                            This is a different issue or incident from the open claim(s) listed above.
                        </label>
                    </div>
                @endif
                <button type="submit" class="btn btn-action btn-action--primary">Submit claim</button>
                <a href="{{ route('dtc.show', $dtc) }}" class="btn btn-action">Cancel</a>
            </form>
        </div>
    </div>
</div>
@endsection
