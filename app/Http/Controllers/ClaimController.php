<?php

namespace App\Http\Controllers;

use App\Mail\ClaimStatusUpdated;
use App\Mail\ClaimAssignedToStaff;
use App\Models\ClaimAttachment;
use App\Models\ClaimEmailDelivery;
use App\Models\Claim;
use App\Models\Dtc;
use App\Models\DtcAuditLog;
use App\Models\User;
use App\Services\ClaimSubmissionNotifier;
use App\Services\ClaimSubmissionNotificationException;
use App\Services\ClaimStaffNotifier;
use App\Services\ClaimStaffNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ClaimController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $followUpCutoff = now()->copy()->subDays(3)->toDateTimeString();
        $overdueCutoff = now(config('dtc.timezone'))->subDays(3)->toDateTimeString();
        $claims = $this->filteredClaims($filters, $followUpCutoff, $overdueCutoff)->with(['registrant', 'assignedStaff']);

        return view('claims.index', [
            'claims' => $claims->latest('id')->paginate(25)->withQueryString(),
            'statuses' => Claim::STATUSES,
            'selectedStatus' => $filters['status'] ?? '',
            'selectedQuickView' => $filters['quick_view'] ?? '',
            'submittedClaimsCount' => Claim::where('status', 'submitted')->count(),
            'activeClaimsCount' => Claim::whereIn('status', Claim::OPEN_STATUSES)->count(),
            'totalClaimsCount' => Claim::count(),
            'followUpClaimsCount' => $this->followUpClaims($followUpCutoff)->count(),
            'failedNotificationCount' => Claim::where('submission_notification_status', 'failed')
                ->orWhere('staff_notification_status', 'failed')
                ->count(),
            'followUpCutoff' => $followUpCutoff,
            'overdueCutoff' => $overdueCutoff,
            'overdueClaimsCount' => Claim::where('status', 'submitted')->where('created_at', '<=', $overdueCutoff)->count(),
        ]);
    }

    public function report(Request $request): View
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date', 'before_or_equal:date_to'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from', 'before_or_equal:'.now(config('dtc.timezone'))->toDateString()],
        ]);
        $dateFrom = $filters['date_from'] ?? now(config('dtc.timezone'))->subMonths(11)->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? now(config('dtc.timezone'))->toDateString();
        $start = \Illuminate\Support\Carbon::parse($dateFrom, config('dtc.timezone'))->startOfDay();
        $end = \Illuminate\Support\Carbon::parse($dateTo, config('dtc.timezone'))->endOfDay();
        $claims = Claim::query()->whereBetween('created_at', [$start, $end]);
        $statusCounts = (clone $claims)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $monthlySubmitted = [];

        (clone $claims)->select(['id', 'created_at'])->orderBy('id')->chunkById(500, function ($chunk) use (&$monthlySubmitted) {
            foreach ($chunk as $claim) {
                $month = $claim->created_at->format('Y-m');
                $monthlySubmitted[$month] = ($monthlySubmitted[$month] ?? 0) + 1;
            }
        });

        ksort($monthlySubmitted);

        return view('claims.report', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'totalClaims' => $statusCounts->sum(),
            'openClaims' => $statusCounts->only(Claim::OPEN_STATUSES)->sum(),
            'closedClaims' => $statusCounts->only(['approved', 'rejected', 'resolved'])->sum(),
            'statusCounts' => $statusCounts,
            'statuses' => Claim::STATUSES,
            'monthlySubmitted' => $monthlySubmitted,
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $followUpCutoff = now()->copy()->subDays(3)->toDateTimeString();
        $overdueCutoff = now(config('dtc.timezone'))->subDays(3)->toDateTimeString();

        return response()->streamDownload(function () use ($filters, $followUpCutoff, $overdueCutoff) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Claim No.', 'First Name', 'Last Name', 'DTC Code', 'Plate Number',
                'Subject', 'Description', 'Incident Date', 'Affected Tyres', 'Status', 'Eligibility', 'Eligibility Reason',
                'Submitted At', 'Status History', 'Customer Messages', 'Internal Notes',
            ]);

            $this->filteredClaims($filters, $followUpCutoff, $overdueCutoff)
                ->with(['registrant', 'histories'])
                ->chunkById(100, function ($claims) use ($out) {
                    foreach ($claims as $claim) {
                        $registrant = $claim->registrant;
                        $history = $claim->histories
                            ->whereIn('event_type', ['submitted', 'status_changed'])
                            ->map(function ($entry) {
                                $status = Claim::STATUSES[$entry->to_status] ?? $entry->to_status;

                                return $entry->created_at->format('Y-m-d H:i').' '.$status;
                            })
                            ->implode(' | ');
                        $notes = $claim->histories
                            ->filter(fn ($entry) => filled($entry->note))
                            ->map(fn ($entry) => $entry->created_at->format('Y-m-d H:i').' '.$entry->note)
                            ->implode(' | ');
                        $customerMessages = $claim->histories
                            ->filter(fn ($entry) => filled($entry->customer_message))
                            ->map(fn ($entry) => $entry->created_at->format('Y-m-d H:i').' '.$entry->customer_message)
                            ->implode(' | ');

                        $row = [
                            $claim->claim_number,
                            $registrant?->firstname,
                            $registrant?->lastname,
                            $registrant?->dtc_card_no,
                            $registrant?->vehicle_reg_id,
                            $claim->subject,
                            $claim->description,
                            $claim->incident_date?->format('Y-m-d'),
                            $claim->affected_tyres,
                            Claim::STATUSES[$claim->status] ?? $claim->status,
                            $claim->eligible ? 'Eligible' : 'Manual review',
                            $claim->eligibility_reason,
                            $claim->created_at?->format('Y-m-d H:i:s'),
                            $history,
                            $customerMessages,
                            $notes,
                        ];
                        fputcsv($out, array_map([$this, 'safeCsvCell'], $row));
                    }

                    if (ob_get_length()) {
                        ob_flush();
                    }
                    flush();
                });

            fclose($out);
        }, 'claims_'.date('Y-m-d_His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(Claim::STATUSES))],
            'date_from' => ['nullable', 'date', 'before_or_equal:'.now(config('dtc.timezone'))->toDateString()],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from', 'before_or_equal:'.now(config('dtc.timezone'))->toDateString()],
            'quick_view' => ['nullable', Rule::in(['submitted', 'under_review', 'need_more_info', 'follow_up', 'overdue', 'notification_failed', 'approved', 'rejected'])],
        ]);
    }

    private function filteredClaims(array $filters, string $followUpCutoff, string $overdueCutoff): Builder
    {
        $claims = Claim::query();
        if (! empty($filters['search'])) {
            $term = trim($filters['search']);
            $claims->where(function ($query) use ($term) {
                $query->where('claim_number', 'like', '%'.addcslashes($term, '%_\\').'%')
                    ->orWhereHas('registrant', fn ($registrant) => $registrant->where('dtc_card_no', strtoupper($term)));
            });
        }
        if (! empty($filters['status'])) {
            $claims->where('status', $filters['status']);
        }
        if (! empty($filters['date_from'])) {
            $claims->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $claims->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (($filters['quick_view'] ?? null) === 'follow_up') {
            $claims->whereIn('status', Claim::OPEN_STATUSES);
            $this->applyFollowUpCutoff($claims, $followUpCutoff);
        } elseif (($filters['quick_view'] ?? null) === 'overdue') {
            $claims->where('status', 'submitted')->where('created_at', '<=', $overdueCutoff);
        } elseif (($filters['quick_view'] ?? null) === 'notification_failed') {
            $claims->where(function ($query) {
                $query->where('submission_notification_status', 'failed')
                    ->orWhere('staff_notification_status', 'failed');
            });
        } elseif (! empty($filters['quick_view'])) {
            $claims->where('status', $filters['quick_view']);
        }

        return $claims;
    }

    private function followUpClaims(string $followUpCutoff): Builder
    {
        $claims = Claim::whereIn('status', Claim::OPEN_STATUSES);
        $this->applyFollowUpCutoff($claims, $followUpCutoff);

        return $claims;
    }

    private function applyFollowUpCutoff(Builder $claims, string $followUpCutoff): void
    {
        $claims->where('updated_at', '<=', $followUpCutoff);
    }

    private function safeCsvCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    public function create(Dtc $dtc): View
    {
        [$eligible, $eligibilityReason] = Claim::eligibilityFor($dtc);
        $openClaims = $dtc->claims()
            ->whereIn('status', Claim::OPEN_STATUSES)
            ->latest('id')
            ->get();

        return view('claims.create', compact('dtc', 'eligible', 'eligibilityReason', 'openClaims'));
    }

    public function store(
        Request $request,
        Dtc $dtc,
        ClaimSubmissionNotifier $notifier,
        ClaimStaffNotifier $staffNotifier,
        ClaimStaffNotifications $staffNotifications
    ): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:5000'],
            'incident_date' => ['nullable', 'date', 'before_or_equal:'.now(config('dtc.timezone'))->toDateString()],
            'affected_tyres' => ['nullable', 'string', 'max:160'],
            'attachments' => ['nullable', 'array', 'max:1'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ]);

        if ($dtc->date_of_purchase) {
            $request->validate([
                'incident_date' => [
                    'nullable',
                    'after_or_equal:'.$dtc->date_of_purchase->toDateString(),
                ],
            ], [
                'incident_date.after_or_equal' => 'The issue date cannot be before the purchase date.',
            ]);
        }

        [$eligible, $eligibilityReason] = Claim::eligibilityForIncident($dtc, $data['incident_date'] ?? null);

        if (! $dtc->email) {
            return redirect()->route('claims.create', $dtc)
                ->withInput()
                ->withErrors([
                    'email_notification' => 'A valid customer email is required before a claim can be submitted. Update the registration email and try again.',
                ]);
        }
        $creator = $request->user();
        $claim = DB::transaction(function () use ($data, $dtc, $eligible, $eligibilityReason, $request, $notifier, $creator) {
            $lockedRegistrant = Dtc::whereKey($dtc->id)->lockForUpdate()->firstOrFail();
            $hasOpenClaims = $lockedRegistrant->claims()
                ->whereIn('status', Claim::OPEN_STATUSES)
                ->exists();

            if ($hasOpenClaims) {
                $request->validate([
                    'different_issue_confirmed' => ['required', 'accepted'],
                ], [
                    'different_issue_confirmed.required' => 'An open claim already exists. Confirm that this is a different issue before submitting another claim.',
                    'different_issue_confirmed.accepted' => 'An open claim already exists. Confirm that this is a different issue before submitting another claim.',
                ]);
            }

            $claim = Claim::create([
                'dtc_id' => $dtc->id,
                'subject' => $data['subject'],
                'description' => $data['description'],
                'incident_date' => $data['incident_date'] ?? null,
                'affected_tyres' => $data['affected_tyres'] ?? null,
                'eligible' => $eligible,
                'eligibility_reason' => $eligibilityReason,
                'status' => 'submitted',
                'created_by' => $creator->id,
            ]);
            if ($creator->role === 'staff') {
                $claim->forceFill([
                    'assigned_to' => $creator->id,
                    'assigned_at' => now(),
                ])->save();
            }
            $claim->update(['claim_number' => 'CLM-'.str_pad((string) $claim->id, 6, '0', STR_PAD_LEFT)]);
            $claim->histories()->create([
                'changed_by' => $request->user()->id,
                'event_type' => 'submitted',
                'to_status' => 'submitted',
            ]);
            $this->storeAttachments($claim, (array) $request->file('attachments', []));
            DtcAuditLog::record(
                $request,
                'claim.submitted',
                'claim',
                $claim->id,
                $dtc->id,
                'Submitted claim '.$claim->claim_number,
                null,
                ['status' => 'submitted']
            );

            try {
                $claim->submission_notification_status = $notifier->send($lockedRegistrant, $claim);
            } catch (ClaimSubmissionNotificationException $exception) {
                $claim->submission_notification_status = 'failed';
                $claim->submission_notification_error = $exception->getMessage();
            }
            $claim->save();

            return $claim;
        });

        $staffNotifications->notify($claim, 'claim_submitted', 'New claim submitted');
        $staffNotifier->send($claim);

        $statusMessage = match ($claim->submission_notification_status) {
            'sent' => 'Claim '.$claim->claim_number.' submitted and confirmation email accepted by the mail server.',
            default => 'Claim '.$claim->claim_number.' submitted, but the confirmation email could not be sent. Check status in Check Warranty.',
        };

        return redirect()->route('claims.show', $claim)->with('status', $statusMessage);
    }

    public function show(Claim $claim): View
    {
        $user = request()->user();
        $claim->load(['registrant', 'histories.changedBy', 'attachments', 'emailDeliveries', 'assignedStaff']);
        $availableStatuses = Claim::TRANSITIONS[$claim->status] ?? [];

        return view('claims.show', [
            'claim' => $claim,
            'statuses' => Claim::STATUSES,
            'availableStatuses' => $availableStatuses,
            'canManageClaim' => $user->isAdmin()
                || ($user->role === 'staff' && (int) $claim->assigned_to === (int) $user->id),
            'staffUsers' => $user->isAdmin()
                ? User::query()->where('role', 'staff')->orderBy('name')->get(['id', 'name'])
                : collect(),
            'isOverdue' => $claim->status === 'submitted'
                && $claim->created_at->lessThanOrEqualTo(now(config('dtc.timezone'))->subDays(3)),
        ]);
    }

    public function openAlert(Request $request, Claim $claim, ClaimStaffNotifications $notifications): RedirectResponse
    {
        abort_unless($request->user()->role === 'staff', 403);
        $notifications->markClaimRead($request->user(), $claim);

        return redirect()->route('claims.show', $claim);
    }

    public function assign(Request $request, Claim $claim): RedirectResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['admin', 'staff'], true), 403);
        if ($user->isAdmin()) {
            $data = $request->validate([
                'assigned_to' => [
                    'nullable',
                    'integer',
                    Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'staff')->whereNull('deleted_at')),
                ],
            ]);
            $assigneeId = isset($data['assigned_to']) ? (int) $data['assigned_to'] : null;
        } else {
            $assigneeId = (int) $user->id;
        }

        $result = DB::transaction(function () use ($claim, $user, $assigneeId, $request) {
            $lockedClaim = Claim::query()->lockForUpdate()->findOrFail($claim->id);
            if (! $user->isAdmin() && $lockedClaim->assigned_to !== null && (int) $lockedClaim->assigned_to !== (int) $user->id) {
                return 'assigned_elsewhere';
            }

            $previousAssigneeId = $lockedClaim->assigned_to;
            if ((int) $previousAssigneeId === (int) $assigneeId) {
                return 'unchanged';
            }

            $lockedClaim->forceFill([
                'assigned_to' => $assigneeId,
                'assigned_at' => $assigneeId === null ? null : now(),
            ])->save();
            $assigneeName = $assigneeId
                ? User::query()->whereKey($assigneeId)->value('name')
                : null;
            DtcAuditLog::record(
                $request,
                'claim.assigned',
                'claim',
                $lockedClaim->id,
                $lockedClaim->dtc_id,
                $assigneeName
                    ? 'Assigned claim '.$lockedClaim->claim_number.' to '.$assigneeName
                    : 'Unassigned claim '.$lockedClaim->claim_number,
                ['assigned_to' => $previousAssigneeId],
                ['assigned_to' => $assigneeId]
            );

            return 'updated';
        });

        if ($result === 'assigned_elsewhere') {
            return redirect()->route('claims.show', $claim)
                ->withErrors(['assignment' => 'This claim is already assigned to another staff member.']);
        }

        if ($result === 'updated' && $assigneeId !== null) {
            $assignee = User::query()->whereKey($assigneeId)->where('role', 'staff')->firstOrFail();
            try {
                Mail::to($assignee->email)->send(new ClaimAssignedToStaff(
                    $assignee->name,
                    $claim->claim_number,
                    $claim->subject,
                    $user->name,
                    route('claims.show', $claim)
                ));
            } catch (Throwable $exception) {
                Log::warning('Claim assignment email failed', [
                    'claim_id' => $claim->id,
                    'assignee_id' => $assignee->id,
                    'error' => class_basename($exception),
                ]);

                return redirect()->route('claims.show', $claim)
                    ->with('status', 'Claim assignment was saved, but the email to '.$assignee->name.' could not be sent. Please check mail delivery and try again.');
            }
        }

        return redirect()->route('claims.show', $claim)->with('status', match ($result) {
            'unchanged' => 'The claim assignment is unchanged.',
            default => $assigneeId === null ? 'Claim assignment cleared.' : 'Claim assignment saved and email sent to the assigned staff member.',
        });
    }

    public function retryNotification(Request $request, Claim $claim, ClaimSubmissionNotifier $notifier): RedirectResponse
    {
        if ($claim->submission_notification_status === 'sent') {
            return redirect()->route('claims.show', $claim)
                ->with('status', 'The customer confirmation email has already been sent.');
        }

        $claim->load('registrant');
        abort_unless($claim->registrant, 404);

        try {
            $claim->submission_notification_status = $notifier->send($claim->registrant, $claim);
            $claim->submission_notification_error = null;
            $claim->save();
            $this->supersedeFailedDeliveries($claim, 'claim_submitted');
        } catch (ClaimSubmissionNotificationException $exception) {
            $claim->submission_notification_status = 'failed';
            $claim->submission_notification_error = $exception->getMessage();
            $claim->save();

            return redirect()->route('claims.show', $claim)
                ->withErrors(['email_notification' => $exception->getMessage()]);
        }

        DtcAuditLog::record(
            $request,
            'claim.notification.manual_retry',
            'claim',
            $claim->id,
            $claim->dtc_id,
            'Manually retried customer confirmation email for claim '.$claim->claim_number
        );

        return redirect()->route('claims.show', $claim)
            ->with('status', 'The customer confirmation email was sent.');
    }

    public function retryStaffNotification(Request $request, Claim $claim, ClaimStaffNotifier $notifier): RedirectResponse
    {
        if ($claim->staff_notification_status === 'sent') {
            return redirect()->route('claims.show', $claim)
                ->with('status', 'The staff notification has already been sent.');
        }

        $sent = $notifier->send($claim);
        if ($sent) {
            $this->supersedeFailedDeliveries($claim, 'staff_new_claim');
        }
        DtcAuditLog::record(
            $request,
            $sent ? 'claim.staff_retry' : 'claim.staff_retry_failed',
            'claim',
            $claim->id,
            $claim->dtc_id,
            ($sent ? 'Manually retried' : 'Failed to retry').' staff notification for claim '.$claim->claim_number
        );

        return redirect()->route('claims.show', $claim)->with(
            $sent ? 'status' : 'error',
            $sent ? 'The staff notification was sent.' : 'The staff notification could not be sent. Select one or more recipients in Staff Alerts settings, then retry.'
        );
    }

    public function retryStatusNotification(Request $request, Claim $claim, ClaimEmailDelivery $delivery): RedirectResponse
    {
        abort_unless($delivery->claim_id === $claim->id && $delivery->type === 'claim_status_update', 404);
        if ($delivery->status === 'sent') {
            return redirect()->route('claims.show', $claim)
                ->with('status', 'This customer status notification has already been sent.');
        }

        $claim->load('registrant');
        $email = $claim->registrant?->email;
        if (! $email) {
            return redirect()->route('claims.show', $claim)
                ->withErrors(['email_notification' => 'A customer email is required before retrying this notification.']);
        }

        $history = $claim->histories()->find($delivery->claim_history_id);
        if (! $history) {
            return redirect()->route('claims.show', $claim)
                ->withErrors(['email_notification' => 'No customer status update is available to resend.']);
        }

        try {
            Mail::to($email)->send(new ClaimStatusUpdated(
                $claim->claim_number,
                Claim::STATUSES[$history->to_status] ?? $history->to_status,
                $claim->registrant->dtc_card_no,
                $history->customer_message,
            ));
            $delivery->update([
                'status' => 'sent',
                'error' => null,
                'sent_at' => now(),
            ]);
            DtcAuditLog::record(
                $request,
                'claim.status_notification.manual_retry',
                'claim',
                $claim->id,
                $claim->dtc_id,
                'Manually retried status email for claim '.$claim->claim_number
            );
        } catch (Throwable $exception) {
            $delivery->update([
                'status' => 'failed',
                'error' => 'The customer status email could not be delivered.',
            ]);
            DtcAuditLog::record(
                $request,
                'claim.status_notification.manual_retry_failed',
                'claim',
                $claim->id,
                $claim->dtc_id,
                'Failed to retry status email for claim '.$claim->claim_number
            );
            Log::warning('Claim status notification retry failed', [
                'claim_id' => $claim->id,
                'error' => class_basename($exception),
            ]);

            return redirect()->route('claims.show', $claim)
                ->withErrors(['email_notification' => 'The customer status email could not be sent. Please try again.']);
        }

        return redirect()->route('claims.show', $claim)
            ->with('status', 'The customer status notification was resent.');
    }

    public function downloadAttachment(Request $request, Claim $claim, ClaimAttachment $attachment)
    {
        abort_unless($attachment->claim_id === $claim->id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);
        DtcAuditLog::record(
            $request,
            'claim.attachment.downloaded',
            'claim',
            $claim->id,
            $claim->dtc_id,
            'Downloaded claim attachment '.$attachment->original_name.' for claim '.$claim->claim_number
        );

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    public function previewAttachment(Request $request, Claim $claim, ClaimAttachment $attachment)
    {
        abort_unless($attachment->claim_id === $claim->id, 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        $mimeType = Storage::disk($attachment->disk)->mimeType($attachment->path);
        abort_unless(
            in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true)
                && $attachment->mime_type === $mimeType,
            404
        );

        DtcAuditLog::record(
            $request,
            'claim.attachment.previewed',
            'claim',
            $claim->id,
            $claim->dtc_id,
            'Previewed claim attachment '.$attachment->original_name.' for claim '.$claim->claim_number
        );

        return response()->file(Storage::disk($attachment->disk)->path($attachment->path), [
            'Content-Type' => $mimeType,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function storeAttachments(Claim $claim, array $files): void
    {
        foreach ($files as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs(
                'private/claims/'.$claim->id,
                Str::uuid().'.'.$extension,
                'local'
            );
            if (! $path) {
                throw new \RuntimeException('Unable to save the claim attachment.');
            }

            try {
                $claim->attachments()->create([
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => basename($file->getClientOriginalName()),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            } catch (Throwable $exception) {
                Storage::disk('local')->delete($path);
                throw $exception;
            }
        }
    }

    private function supersedeFailedDeliveries(Claim $claim, string $type): void
    {
        $claim->emailDeliveries()
            ->where('type', $type)
            ->where('status', 'failed')
            ->update([
                'status' => 'superseded',
                'error' => 'A later manual notification retry succeeded.',
                'next_attempt_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function updateStatus(Request $request, Claim $claim): RedirectResponse
    {
        $this->authorizeClaimManagement($request, $claim);

        $data = $request->validate([
            'status' => ['required', Rule::in(Claim::TRANSITIONS[$claim->status] ?? [])],
            'note' => ['required', 'string', 'max:2000'],
            'customer_message' => ['nullable', 'string', 'max:2000', Rule::requiredIf(fn () => $request->input('status') === 'need_more_info')],
        ]);

        $history = DB::transaction(function () use ($claim, $data, $request) {
            $lockedClaim = Claim::query()->lockForUpdate()->findOrFail($claim->id);
            $this->authorizeClaimManagement($request, $lockedClaim);
            $previousStatus = $lockedClaim->status;
            $lockedClaim->update(['status' => $data['status']]);
            $history = $lockedClaim->histories()->create([
                'changed_by' => $request->user()->id,
                'event_type' => 'status_changed',
                'from_status' => $previousStatus,
                'to_status' => $data['status'],
                'note' => $data['note'],
                'customer_message' => $data['customer_message'] ?? null,
            ]);
            DtcAuditLog::record(
                $request,
                'claim.status_changed',
                'claim',
                $lockedClaim->id,
                $lockedClaim->dtc_id,
                'Updated claim '.$lockedClaim->claim_number.' status: '.
                    (Claim::STATUSES[$previousStatus] ?? $previousStatus).' to '.
                    (Claim::STATUSES[$data['status']] ?? $data['status']),
                ['status' => $previousStatus],
                ['status' => $data['status']]
            );

            return $history;
        });

        $claim->refresh();
        $claim->load('registrant');
        $email = $claim->registrant?->email;
        if (! $email) {
            return redirect()->route('claims.show', $claim)
                ->with('status', 'Claim status updated. No registered email is available; the customer can see the update in Check Warranty.');
        }
        try {
            Mail::to($email)->send(new ClaimStatusUpdated(
                $claim->claim_number,
                Claim::STATUSES[$claim->status] ?? $claim->status,
                $claim->registrant->dtc_card_no,
                $history->customer_message,
            ));
            $claim->emailDeliveries()->create([
                'type' => 'claim_status_update',
                'claim_history_id' => $history->id,
                'recipient_hash' => hash('sha256', strtolower(trim($email))),
                'status' => 'sent',
                'sent_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $claim->emailDeliveries()->create([
                'type' => 'claim_status_update',
                'claim_history_id' => $history->id,
                'recipient_hash' => hash('sha256', strtolower(trim($email))),
                'status' => 'failed',
                'error' => 'The customer status email could not be delivered.',
            ]);
            Log::warning('Claim status notification email failed', [
                'claim_id' => $claim->id,
                'error' => class_basename($exception),
            ]);

            return redirect()->route('claims.show', $claim)->with('status', 'Claim status updated, but the notification email could not be sent. The customer can still see the update in Check Warranty.');
        }

        return redirect()->route('claims.show', $claim)->with('status', 'Claim status updated and the customer was emailed.');
    }

    public function addNote(Request $request, Claim $claim): RedirectResponse
    {
        $this->authorizeClaimManagement($request, $claim);

        $data = $request->validate([
            'note' => ['required', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($claim, $request, $data) {
            $lockedClaim = Claim::query()->lockForUpdate()->findOrFail($claim->id);
            $this->authorizeClaimManagement($request, $lockedClaim);
            $lockedClaim->histories()->create([
                'changed_by' => $request->user()->id,
                'event_type' => 'note_added',
                'note' => $data['note'],
            ]);
            DtcAuditLog::record(
                $request,
                'claim.note_added',
                'claim',
                $lockedClaim->id,
                $lockedClaim->dtc_id,
                'Added an internal note to claim '.$lockedClaim->claim_number
            );
            $lockedClaim->touch();
        });

        return redirect()->route('claims.show', $claim)->with('status', 'Claim note added.');
    }

    private function authorizeClaimManagement(Request $request, Claim $claim): void
    {
        $user = $request->user();
        abort_unless(
            $user->isAdmin()
                || ($user->role === 'staff' && (int) $claim->assigned_to === (int) $user->id),
            403
        );
    }
}
