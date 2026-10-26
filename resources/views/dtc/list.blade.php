@extends('layouts.app')
@section('title', 'Registrants')

@section('content')
<div class="container">
    <div class="registrants-header">
        <div>
            <h1>Registrants</h1>
            <p>Manage and review DTC registrations.</p>
        </div>
        <div class="registrants-count">
            <strong>{{ number_format($users->total()) }}</strong>
            <span>{{ $users->total() === 1 ? 'registrant' : 'registrants' }}</span>
        </div>
    </div>

    <div class="registrants-toolbar">
        <a href="#" class="btn btn-action btn-action--primary" data-toggle="modal" data-target="#dtcUserReport"><span class="fa fa-download" aria-hidden="true"></span> &nbsp;Export DTC Users</a>
        <form method="GET" action="{{ route('dtc.lists') }}" class="registrants-search">
            <label class="sr-only" for="registrant-search">Search registrants</label>
            <div class="input-group">
                <input id="registrant-search" type="search" class="form-control" name="search" placeholder="DTC Code or Registration ID" value="{{ request('search') }}" aria-label="Search by DTC Code or Registration ID">
                <span class="input-group-btn">
                    <button type="submit" class="btn btn-action btn-action--primary"><span class="fa fa-search" aria-hidden="true"></span><span class="sr-only">Search</span></button>
                </span>
            </div>
            @if (request('search'))
                <a class="registrants-clear-search" href="{{ route('dtc.lists') }}">Clear search</a>
            @endif
        </form>
    </div>

    <div class="registrants-table-wrap">
        <div class="table-responsive">
            <table class="table table-striped registrants-table">
                <caption class="sr-only">DTC registrants</caption>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>First name</th>
                        <th>Last name</th>
                        <th>Registration ID</th>
                        <th>Purchase date</th>
                        <th>E-mail</th>
                        <th>{{ Auth::user()->isAdmin() ? 'Actions' : 'View' }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td><a class="registrant-id" href="{{ route('dtc.show', $user) }}">{{ $user->id }}</a></td>
                        <td>{{ $user->firstname }}</td>
                        <td>{{ $user->lastname }}</td>
                        <td>{{ $user->vehicle_reg_id }}</td>
                        <td>{{ $user->date_of_purchase->format('Y-m-d') }}</td>
                        <td>{{ $user->email }}</td>
                        <td class="registrant-actions">
                            <a href="{{ route('dtc.show', $user) }}" class="btn btn-sm btn-action btn-action--primary" aria-label="View {{ $user->firstname }} {{ $user->lastname }}">View</a>
                            @if (Auth::user()->isAdmin())
                                <button type="button" class="btn btn-sm btn-action btn-action--danger" data-toggle="modal" data-target="#deleteRegistrantModal" data-delete-url="{{ route('dtc.destroy', $user) }}">Delete</button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="registrants-empty">
                        <span class="fa fa-search" aria-hidden="true"></span>
                        <strong>No registrants found.</strong>
                        <span>Try another search or clear your filters.</span>
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="registrants-pagination">{{ $users->links() }}</div>
    </div>
</div>

@if (Auth::user()->isAdmin())
<div class="modal fade" id="deleteRegistrantModal" tabindex="-1" role="dialog" aria-labelledby="deleteRegistrantModalLabel">
    <div class="modal-dialog" role="document" style="margin-top: 80px;">
        <form id="deleteRegistrantForm" method="POST" action="#">
            @csrf
            @method('DELETE')
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="deleteRegistrantModalLabel">Delete registration</h4>
                </div>
                <div class="modal-body text-center">
                    <p>Are you sure you want to delete this registration?</p>
                </div>
                <div class="modal-footer text-center">
                    <button type="button" class="btn btn-action" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-action btn-action--danger">Delete</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

<div class="modal fade" id="dtcUserReport" tabindex="-1" role="dialog" aria-labelledby="dtcUserReportLabel">
    <div class="modal-dialog" role="document">
        <form action="{{ route('dtc.export') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="dtcUserReportLabel">Download DTC User Report</h4>
                </div>
                <div class="modal-body">
                    <label>Date of Registration</label>
                    <div class="row">
                        <div class="col-md-5"><input type="date" name="date_from" class="form-control" aria-label="From" max="{{ today()->toDateString() }}" required></div>
                        <div class="col-md-5"><input type="date" name="date_to" class="form-control" aria-label="To" max="{{ today()->toDateString() }}" required></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-action" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-action btn-action--primary">Download</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $('#deleteRegistrantModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        $(this).find('form').attr('action', button.data('delete-url'));
    });
</script>
@endpush

@push('styles')
<style>
    .registrants-header { display: flex; align-items: center; justify-content: space-between; gap: 20px; margin: 32px 0 24px; padding-bottom: 20px; border-bottom: 1px solid #e5e5e5; }
    .registrants-header h1 { margin: 0 0 6px; color: #111; font-size: 30px; font-weight: 700; }
    .registrants-header p { margin: 0; color: #666; }
    .registrants-count { display: flex; align-items: baseline; gap: 8px; padding: 10px 14px; border: 1px solid #ddd; border-radius: 4px; color: #555; white-space: nowrap; }
    .registrants-count strong { color: #111; font-size: 22px; }
    .registrants-toolbar { display: flex; align-items: flex-start; justify-content: space-between; gap: 20px; margin-bottom: 18px; }
    .registrants-search { width: min(100%, 440px); }
    .registrants-search .form-control { height: 40px; }
    .registrants-search .btn { height: 40px; min-width: 48px; }
    .registrants-clear-search { display: inline-block; margin-top: 6px; color: #333; font-size: 12px; }
    .registrants-table-wrap { overflow: hidden; margin-bottom: 32px; border: 1px solid #e5e5e5; border-radius: 5px; background: #fff; }
    .registrants-table { margin-bottom: 0; }
    .registrants-table > thead > tr > th { padding: 14px 12px; border-bottom: 1px solid #222; background: #f5f5f5; color: #222; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; white-space: nowrap; }
    .registrants-table > tbody > tr > td { padding: 14px 12px; vertical-align: middle; }
    .registrants-table > tbody > tr:hover { background: #f8f8f8; }
    .registrant-id { color: #666; font-weight: 600; }
    .registrant-actions { text-align: right; white-space: nowrap; }
    .registrant-actions .btn { margin-left: 4px; }
    .registrants-empty { padding: 42px 15px !important; text-align: center; color: #777; }
    .registrants-empty .fa { display: block; margin-bottom: 12px; color: #999; font-size: 24px; }
    .registrants-empty strong, .registrants-empty span { display: block; }
    .registrants-empty strong { margin-bottom: 5px; color: #222; }
    .registrants-pagination { padding: 0 15px 20px; text-align: center; }
    .registrants-pagination .pagination { margin: 18px 0 0; }
    .registrants-pagination .pagination > .active > span { border-color: #111; background: #111; }
    @media (max-width: 767px) {
        .registrants-header { align-items: flex-start; margin-top: 22px; }
        .registrants-header h1 { font-size: 25px; }
        .registrants-count { flex-direction: column; gap: 0; padding: 7px 10px; text-align: center; }
        .registrants-count strong { font-size: 18px; }
        .registrants-toolbar { flex-direction: column; gap: 12px; }
        .registrants-search { width: 100%; }
    }
</style>
@endpush
