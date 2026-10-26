@extends('layouts.app')
@section('title', 'User details')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <h3>
                User details
                <span class="pull-right">
                    <a href="{{ route('claims.create', $user) }}" class="btn btn-action">Create claim</a>
                    @if (Auth::user()->isAdmin())
                        <a href="{{ route('dtc.edit', $user) }}" class="btn btn-action btn-action--primary">Edit</a>
                    @endif
                </span>
            </h3>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <table class="table table-striped dtc-user-details-table">
                <tr><td class="label-col">Firstname</td><td>{{ $user->firstname }}</td></tr>
                <tr><td>Lastname</td><td>{{ $user->lastname }}</td></tr>
                <tr><td>Email</td><td>{{ $user->email }}</td></tr>
                <tr><td>Contact Number</td><td>{{ $user->contact_no }}</td></tr>
                <tr><td>Registration ID</td><td>{{ $user->vehicle_reg_id }}</td></tr>
                <tr><td>Brand</td><td>{{ $user->brand }}</td></tr>
                <tr><td>Model</td><td>{{ $user->model }}</td></tr>
                <tr><td>Mileage</td><td>{{ $user->mileage }}</td></tr>
                <tr><td>Tyre</td><td>{{ $user->tyre }}</td></tr>
                <tr><td>Tyre Size</td><td>{{ $user->tyre_size }}</td></tr>
                <tr><td>Rim Size</td><td>{{ $user->rim_size }}</td></tr>
                <tr><td>Quantity</td><td>{{ $user->quantity }}</td></tr>
                <tr><td>Date of Purchase</td><td>{{ $user->date_of_purchase->format('Y-m-d') }}</td></tr>
                <tr><td>Store Name</td><td>{{ $user->store_name }}</td></tr>
                <tr><td>State</td><td>{{ $user->state }}</td></tr>
                <tr><td>DTC Code</td><td>{{ $user->dtc_card_no }}</td></tr>
                <tr><td>Subscribe</td><td>{{ $user->subscribe ? 'Yes' : 'No' }}</td></tr>
            </table>
        </div>
    </div>

    @if ($promo = $user->promos->firstWhere('promo_key', 'promo'))
        <section class="dtc-promo-details" aria-labelledby="dtc-promo-details-title">
            <h4 id="dtc-promo-details-title">Promo</h4>
            <table class="table dtc-promo-details-table">
                <tbody>
                    <tr><td>Promo</td><td>{{ $promo->promo_value }}</td></tr>
                    @if ($assistedPerson = $user->promos->firstWhere('promo_key', 'assisted_person'))
                        <tr><td>Assisted Person</td><td>{{ $assistedPerson->promo_value }}</td></tr>
                    @endif
                    @if ($confirm = $user->promos->firstWhere('promo_key', 'confirm'))
                        <tr><td>Confirm</td><td>{{ $confirm->promo_value }}</td></tr>
                    @endif
                    @if ($terms = $user->promos->firstWhere('promo_key', 'terms'))
                        <tr><td>Terms</td><td>{{ $terms->promo_value }}</td></tr>
                    @endif
                    @if ($invoice = $user->promos->firstWhere('promo_key', 'invoice'))
                        <tr>
                            <td>Invoice</td>
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-action dtc-promo-invoice-button"
                                    data-toggle="modal"
                                    data-target="#dtcPromoInvoiceModal"
                                    data-invoice-url="{{ route('dtc.promo-invoice', $user) }}"
                                >View Invoice</button>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </section>
    @endif

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Warranty eligibility</strong></div>
        <div class="panel-body dtc-user-details-warranty">
            @if ($warrantyEligible)
                <span class="label label-default">Within warranty</span>
                <span>{{ $warrantyEligibilityReason }}</span>
            @else
                <span class="label label-default">Outside warranty / needs review</span>
                <span>{{ $warrantyEligibilityReason }}</span>
            @endif
            <p class="help-block">Eligibility is based on a 12-month period from the purchase date. Claims may still be submitted for manual review.</p>
        </div>
    </div>

    @if ($user->claims->isNotEmpty())
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Claims</strong> <span class="text-muted">&middot; Multiple claims can be filed for this DTC Code; each claim has its own status and history.</span></div>
            <div class="table-responsive">
                <table class="table table-striped" style="margin-bottom:0">
                    <thead><tr><th>Claim No.</th><th>Subject</th><th>Status</th><th>Eligibility</th></tr></thead>
                    <tbody>
                    @foreach ($user->claims as $claim)
                        <tr>
                            <td><a href="{{ route('claims.show', $claim) }}">{{ $claim->claim_number }}</a></td>
                            <td>{{ $claim->subject }}</td>
                            <td>{{ \App\Models\Claim::STATUSES[$claim->status] ?? $claim->status }}</td>
                            <td>{{ $claim->eligible ? 'Eligible' : 'Manual review' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <a href="{{ route('dtc.lists') }}" class="btn btn-action">Back</a>
    <br><br>
</div>

@if ($user->promos->firstWhere('promo_key', 'invoice'))
    <div class="modal fade dtc-promo-invoice-modal" id="dtcPromoInvoiceModal" tabindex="-1" role="dialog" aria-labelledby="dtcPromoInvoiceTitle">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="dtcPromoInvoiceTitle">Promotion invoice</h4>
                </div>
                <div class="modal-body">
                    <iframe id="dtcPromoInvoiceFrame" title="Promotion invoice preview" src="about:blank"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-action" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection

@push('styles')
<style>
    .dtc-user-details-table > tbody > tr > td {
        color: #20252b;
        font-size: 15px;
        line-height: 1.5;
    }
    .dtc-user-details-table > tbody > tr > td:first-child {
        width: 25%;
        color: #364152;
        font-weight: 600;
    }
    .dtc-user-details-table > tbody > tr > td:last-child {
        font-weight: 500;
    }
    .dtc-user-details-warranty {
        color: #364152;
        font-size: 15px;
    }
    .dtc-user-details-warranty .help-block {
        margin-top: 10px;
        color: #596579;
        font-size: 14px;
    }
    .dtc-promo-details {
        margin: 24px 0;
        overflow: hidden;
        border: 1px solid #e1e6ed;
        border-radius: 9px;
        background: #fff;
        box-shadow: 0 2px 8px rgba(27, 47, 75, .035);
    }
    .dtc-promo-details > h4 {
        margin: 0;
        padding: 17px 20px 10px;
        color: #1b3761;
        font-size: 17px;
        font-weight: 600;
    }
    .dtc-promo-details-table {
        margin: 0;
        color: #142b4a;
        font-size: 14px;
    }
    .dtc-promo-details-table > tbody > tr > td {
        height: 49px;
        padding: 12px 14px;
        border-top: 1px solid #e8edf3;
        vertical-align: middle;
    }
    .dtc-promo-details-table > tbody > tr:nth-child(odd) {
        background: #f8fafc;
    }
    .dtc-promo-details-table > tbody > tr > td:first-child {
        width: 25%;
        color: #1b3761;
        font-weight: 500;
    }
    .dtc-promo-details-table > tbody > tr > td:last-child {
        color: #101c2d;
        font-weight: 500;
    }
    .dtc-promo-invoice-modal .modal-body {
        padding: 0;
        background: #f2f4f7;
    }
    .dtc-promo-invoice-modal iframe {
        display: block;
        width: 100%;
        height: 75vh;
        max-height: 850px;
        border: 0;
        background: #fff;
    }
    @media (max-width: 767px) {
        .dtc-promo-details-table > tbody > tr > td:first-child {
            width: 36%;
        }
        .dtc-promo-invoice-modal .modal-dialog {
            margin: 10px;
        }
        .dtc-promo-invoice-modal iframe {
            height: 70vh;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    $('#dtcPromoInvoiceModal')
        .on('show.bs.modal', function (event) {
            var url = $(event.relatedTarget).data('invoice-url');
            $('#dtcPromoInvoiceFrame').attr('src', url);
        })
        .on('hidden.bs.modal', function () {
            $('#dtcPromoInvoiceFrame').attr('src', 'about:blank');
        });
</script>
@endpush
