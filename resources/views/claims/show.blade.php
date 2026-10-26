@extends('layouts.app')
@section('title', $claim->claim_number)

@section('content')
<div class="container">
    <div class="clearfix" style="margin:30px 0 20px">
        <div class="pull-left">
            <h1 style="margin-top:0">{{ $claim->claim_number }}</h1>
            <p class="text-muted">Submitted {{ $claim->created_at->format('Y-m-d H:i') }}</p>
            @if ($isOverdue)
                <p><span class="label label-danger">Response overdue</span> Submitted more than 3 days ago and still awaiting review.</p>
            @endif
        </div>
        <a href="{{ route('claims.index') }}" class="btn btn-action pull-right">All claims</a>
    </div>

    <div class="row">
        <div class="col-md-7">
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Claim details</strong></div>
                <div class="panel-body">
                    <p><strong>Subject</strong><br>{{ $claim->subject }}</p>
                    @if ($claim->incident_date)
                        <p><strong>Incident date:</strong> {{ $claim->incident_date->format('Y-m-d') }}</p>
                    @endif
                    @if ($claim->affected_tyres)
                        <p><strong>Affected tyre(s):</strong> {{ $claim->affected_tyres }}</p>
                    @endif
                    <p><strong>Description</strong></p>
                    <p>{!! nl2br(e($claim->description)) !!}</p>
                    <hr>
                    <p><strong>Registrant:</strong>
                        @if ($claim->registrant->trashed())
                            {{ $claim->registrant->firstname }} {{ $claim->registrant->lastname }} <span class="text-muted">(registration deleted)</span>
                        @else
                            <a href="{{ route('dtc.show', $claim->registrant) }}">{{ $claim->registrant->firstname }} {{ $claim->registrant->lastname }}</a>
                        @endif
                    </p>
                    <p><strong>DTC Code:</strong> {{ $claim->registrant->dtc_card_no }}</p>
                    <p><strong>Registration ID:</strong> {{ $claim->registrant->vehicle_reg_id }}</p>
                    <p><strong>Purchase date:</strong> {{ $claim->registrant->date_of_purchase?->format('Y-m-d') ?? 'Not recorded' }}</p>
                    <p><strong>Warranty check at submission:</strong>
                        <span class="label label-default">{{ $claim->eligible ? 'Eligible' : 'Manual review' }}</span>
                        {{ $claim->eligibility_reason }}
                    </p>
                    <p><strong>Current status:</strong> <span class="label label-default">{{ $statuses[$claim->status] ?? $claim->status }}</span></p>
                    <p><strong>Assigned staff:</strong> {{ $claim->assignedStaff?->name ?? 'Unassigned' }}</p>
                    @if (Auth::user()->isAdmin())
                        <form method="POST" action="{{ route('claims.assignment', $claim) }}" class="claim-assignment">
                            @csrf
                            <div class="claim-assignment__field">
                                <label for="assigned-to">Assign claim to staff</label>
                                <select id="assigned-to" name="assigned_to" class="form-control">
                                    <option value="">Unassigned</option>
                                    @foreach ($staffUsers as $staffUser)
                                        <option value="{{ $staffUser->id }}" @selected((int) $claim->assigned_to === (int) $staffUser->id)>{{ $staffUser->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-action btn-action--primary">
                                <span class="fa fa-user-plus" aria-hidden="true"></span>
                                Save assignment
                            </button>
                        </form>
                    @elseif (! $claim->assigned_to)
                        <form method="POST" action="{{ route('claims.assignment', $claim) }}" class="claim-assignment claim-assignment--self">
                            @csrf
                            <p class="claim-assignment__hint">Take ownership so the team knows you are handling this claim.</p>
                            <button type="submit" class="btn btn-action btn-action--primary">
                                <span class="fa fa-hand-paper-o" aria-hidden="true"></span>
                                Assign this claim to me
                            </button>
                        </form>
                    @endif
                    @if ($claim->follow_up_reminded_at)
                        <p><strong>Last staff follow-up reminder:</strong> {{ $claim->follow_up_reminded_at->format('Y-m-d H:i') }}</p>
                    @endif
                    <p><strong>Customer notification:</strong>
                        @if ($claim->submission_notification_status === 'sent')
                            <span class="label label-success">Sent</span>
                        @else
                            <span class="label label-warning">{{ ucfirst($claim->submission_notification_status) }}</span>
                            @if ($claim->submission_notification_error)
                                <span>{{ $claim->submission_notification_error }}</span>
                            @endif
                        @endif
                    </p>
                    @if ($claim->submission_notification_status !== 'sent' && filled($claim->registrant?->email))
                        <form method="POST" action="{{ route('claims.notification.retry', $claim) }}">
                            @csrf
                            <button type="submit" class="btn btn-action btn-action--primary">Retry confirmation email</button>
                        </form>
                    @endif
                    @foreach ($claim->emailDeliveries->where('type', 'claim_status_update')->where('status', 'failed') as $delivery)
                        <p class="text-warning" style="margin-top:8px">A customer status email failed to send.</p>
                        <form method="POST" action="{{ route('claims.email-deliveries.retry', [$claim, $delivery]) }}">
                            @csrf
                            <button type="submit" class="btn btn-action btn-action--primary">Retry customer status email</button>
                        </form>
                    @endforeach
                    <p><strong>Staff notification:</strong>
                        @if ($claim->staff_notification_status === 'sent')
                            <span class="label label-success">Sent</span>
                        @else
                            <span class="label label-warning">{{ ucfirst($claim->staff_notification_status) }}</span>
                            @if ($claim->staff_notification_error)
                                <span>{{ $claim->staff_notification_error }}</span>
                            @endif
                        @endif
                    </p>
                    @if ($claim->staff_notification_status !== 'sent')
                        <form method="POST" action="{{ route('claims.staff-notification.retry', $claim) }}">
                            @csrf
                            <button type="submit" class="btn btn-action btn-action--primary">Retry staff notification</button>
                        </form>
                    @endif
                </div>
            </div>
            @if ($claim->attachments->isNotEmpty())
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Customer attachments</strong></div>
                    <ul class="list-group">
                        @foreach ($claim->attachments as $attachment)
                            <li class="list-group-item">
                                @if (in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true))
                                    <span>{{ $attachment->original_name }}</span>
                                @else
                                    <a href="{{ route('claims.attachments.download', [$claim, $attachment]) }}">{{ $attachment->original_name }}</a>
                                @endif
                                <span class="text-muted">({{ number_format($attachment->size / 1024, 0) }} KB)</span>
                                @if (in_array($attachment->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true))
                                    <div style="margin-top:8px">
                                        <button
                                            type="button"
                                            class="btn btn-sm btn-action claim-attachment-preview"
                                            data-toggle="modal"
                                            data-target="#claimAttachmentPreview"
                                            data-preview-url="{{ route('claims.attachments.preview', [$claim, $attachment]) }}"
                                            data-preview-name="{{ $attachment->original_name }}"
                                        >View image</button>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
        <div class="col-md-5">
            @if ($canManageClaim && count($availableStatuses))
                <div class="panel panel-default">
                    <div class="panel-heading"><strong>Update status</strong></div>
                    <div class="panel-body">
                        <form method="POST" action="{{ route('claims.status', $claim) }}">
                            @csrf
                            @method('PUT')
                            <div class="form-group">
                                <label for="status">New status</label>
                                <select id="status" name="status" class="form-control" required>
                                    @foreach ($availableStatuses as $status)
                                        <option value="{{ $status }}">{{ $statuses[$status] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status-note">Reason / note</label>
                                <textarea id="status-note" name="note" class="form-control" rows="3" maxlength="2000" required></textarea>
                                <p class="help-block">Internal only; not shown to the customer.</p>
                            </div>
                            <div class="form-group">
                                <label for="customer-message">Message to customer</label>
                                <textarea id="customer-message" name="customer_message" class="form-control" rows="3" maxlength="2000"></textarea>
                                <p class="help-block">Required when selecting “Need More Info.” This message appears in Check Warranty and is emailed when an email address is available.</p>
                            </div>
                            <button type="submit" class="btn btn-action btn-action--primary">Save status</button>
                        </form>
                    </div>
                </div>
            @endif

            @if ($canManageClaim)
            <div class="panel panel-default">
                <div class="panel-heading"><strong>Add internal note</strong></div>
                <div class="panel-body">
                    <form method="POST" action="{{ route('claims.notes', $claim) }}">
                        @csrf
                        <div class="form-group">
                            <label class="sr-only" for="claim-note">Note</label>
                            <textarea id="claim-note" name="note" class="form-control" rows="3" maxlength="2000" placeholder="Add a note for the claim history" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-action btn-action--primary">Add note</button>
                    </form>
                </div>
            </div>
            @elseif (Auth::user()->role === 'staff')
                <div class="alert alert-info">
                    @if ($claim->assigned_to)
                        This claim is assigned to {{ $claim->assignedStaff?->name }}. You can view it, but only the assigned staff member can update it or add notes.
                    @else
                        Assign this claim to yourself to update its status or add internal notes.
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading"><strong>Claim history</strong></div>
        <div class="panel-body">
            <ul class="list-unstyled">
                @foreach ($claim->histories as $event)
                    <li style="padding:12px 0;border-bottom:1px solid #eee">
                        <strong>
                            @if ($event->event_type === 'submitted')
                                Claim submitted
                            @elseif ($event->event_type === 'status_changed')
                                Status changed: {{ $statuses[$event->from_status] ?? $event->from_status }} &rarr; {{ $statuses[$event->to_status] ?? $event->to_status }}
                            @elseif ($event->event_type === 'customer_response')
                                Customer provided additional information
                            @else
                                Note added
                            @endif
                        </strong>
                        <span class="text-muted"> &middot; {{ $event->created_at->format('Y-m-d H:i') }} by {{ $event->changedBy?->name ?? (in_array($event->event_type, ['submitted', 'customer_response'], true) ? 'Customer via WordPress' : 'Former user') }}</span>
                        @if ($event->note)
                            <p style="margin:6px 0 0"><strong>Internal note:</strong> {!! nl2br(e($event->note)) !!}</p>
                        @endif
                        @if ($event->customer_message)
                            <p style="margin:6px 0 0"><strong>Customer message:</strong> {!! nl2br(e($event->customer_message)) !!}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>

<div class="modal fade" id="claimAttachmentPreview" tabindex="-1" role="dialog" aria-labelledby="claimAttachmentPreviewTitle">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="claimAttachmentPreviewTitle">Attachment preview</h4>
            </div>
            <div class="modal-body text-center">
                <img id="claimAttachmentPreviewImage" src="" alt="" class="img-responsive center-block" style="max-height:75vh">
            </div>
            <div class="modal-footer">
                <a id="claimAttachmentPreviewDownload" class="btn btn-action btn-action--primary" href="#">Download</a>
                <button type="button" class="btn btn-action" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $('#claimAttachmentPreview').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var previewUrl = button.data('preview-url');
        var fileName = button.data('preview-name');
        var image = $('#claimAttachmentPreviewImage');

        $('#claimAttachmentPreviewTitle').text(fileName);
        image.attr('src', previewUrl).attr('alt', fileName);
        $('#claimAttachmentPreviewDownload')
            .attr('href', button.closest('li').find('a[href]').attr('href') || previewUrl.replace(/\/preview$/, ''))
            .attr('download', fileName);
    }).on('hidden.bs.modal', function () {
        $('#claimAttachmentPreviewImage').attr('src', '').attr('alt', '');
    });
</script>
@endpush
